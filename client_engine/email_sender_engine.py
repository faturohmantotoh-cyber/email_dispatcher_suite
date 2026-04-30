#!/usr/bin/env python3
"""
Email Sender Engine - Client Application
Python-based local Outlook sender that fetches email queue from server

Requirements:
    pip install requests pywin32

Usage:
    python email_sender_engine.py --server http://your-server.com --token YOUR_TOKEN

Features:
    - Connects to local Outlook COM
    - Fetches email queue from server using unique token
    - Sends emails with attachments
    - Reports status back to server
    - Runs as daemon or single execution
"""

import os
import sys
import json
import time
import uuid
import base64
import argparse
import logging
import tempfile
import requests
from datetime import datetime
from pathlib import Path
from typing import List, Dict, Optional

# Windows COM imports for Outlook
import win32com.client
from win32com.client import constants

VERSION = "1.0.0"
ENGINE_ID = str(uuid.uuid4())[:8]

class Config:
    """Configuration class"""
    SERVER_URL = ""
    API_TOKEN = ""
    OUTLOOK_ACCOUNT = ""
    SEND_DELAY_MS = 1000
    MAX_BATCH_SIZE = 10
    AUTO_CHECK_INTERVAL_SEC = 60
    LOG_LEVEL = "INFO"
    
    @classmethod
    def load_from_env(cls):
        """Load configuration from environment variables"""
        cls.SERVER_URL = os.getenv('EMAIL_ENGINE_SERVER', '').rstrip('/')
        cls.API_TOKEN = os.getenv('EMAIL_ENGINE_TOKEN', '')
        cls.OUTLOOK_ACCOUNT = os.getenv('EMAIL_ENGINE_OUTLOOK_ACCOUNT', '')
        cls.SEND_DELAY_MS = int(os.getenv('EMAIL_ENGINE_DELAY', '1000'))
        cls.MAX_BATCH_SIZE = int(os.getenv('EMAIL_ENGINE_BATCH_SIZE', '10'))
        cls.AUTO_CHECK_INTERVAL_SEC = int(os.getenv('EMAIL_ENGINE_INTERVAL', '60'))
        cls.LOG_LEVEL = os.getenv('EMAIL_ENGINE_LOG_LEVEL', 'INFO')

class EmailSenderEngine:
    """Main email sender engine class"""
    
    def __init__(self):
        self.outlook = None
        self.namespace = None
        self.session = requests.Session()
        self.session.headers.update({
            'X-Engine-Token': Config.API_TOKEN,
            'X-Engine-ID': ENGINE_ID,
            'X-Engine-Version': VERSION,
            'Content-Type': 'application/json'
        })
        self.logger = self._setup_logging()
        self.running = False
        
    def _setup_logging(self) -> logging.Logger:
        """Setup logging configuration"""
        log_dir = Path.home() / '.email_engine' / 'logs'
        log_dir.mkdir(parents=True, exist_ok=True)
        
        log_file = log_dir / f'engine_{ENGINE_ID}_{datetime.now().strftime("%Y%m%d")}.log'
        
        logger = logging.getLogger('EmailEngine')
        logger.setLevel(getattr(logging, Config.LOG_LEVEL.upper()))
        
        # File handler
        fh = logging.FileHandler(log_file, encoding='utf-8')
        fh.setLevel(logging.DEBUG)
        
        # Console handler
        ch = logging.StreamHandler()
        ch.setLevel(logging.INFO)
        
        # Formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        fh.setFormatter(formatter)
        ch.setFormatter(formatter)
        
        logger.addHandler(fh)
        logger.addHandler(ch)
        
        return logger
        
    def initialize_outlook(self) -> bool:
        """Initialize Outlook COM connection"""
        try:
            self.logger.info("Initializing Outlook COM connection...")
            self.outlook = win32com.client.Dispatch("Outlook.Application")
            self.namespace = self.outlook.GetNamespace("MAPI")
            
            # Test connection
            version = self.outlook.Version
            self.logger.info(f"Outlook version: {version}")
            
            # Get available accounts
            accounts = self.namespace.Accounts
            self.logger.info(f"Available accounts: {accounts.Count}")
            
            for i in range(1, accounts.Count + 1):
                account = accounts.Item(i)
                self.logger.info(f"  - {account.SmtpAddress}")
                
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to initialize Outlook: {e}")
            return False
            
    def get_sender_account(self) -> Optional[object]:
        """Get sender account object"""
        if not Config.OUTLOOK_ACCOUNT:
            # Use default account
            return self.namespace.Accounts.Item(1)
            
        # Find account by email
        accounts = self.namespace.Accounts
        for i in range(1, accounts.Count + 1):
            account = accounts.Item(i)
            if account.SmtpAddress.lower() == Config.OUTLOOK_ACCOUNT.lower():
                return account
                
        self.logger.warning(f"Account {Config.OUTLOOK_ACCOUNT} not found, using default")
        return self.namespace.Accounts.Item(1)
        
    def fetch_email_queue(self) -> List[Dict]:
        """Fetch pending emails from server queue"""
        try:
            url = f"{Config.SERVER_URL}/api/engine/fetch-queue"
            params = {
                'limit': Config.MAX_BATCH_SIZE,
                'engine_id': ENGINE_ID
            }
            
            self.logger.debug(f"Fetching queue from: {url}")
            response = self.session.get(url, params=params, timeout=30)
            
            if response.status_code == 200:
                data = response.json()
                emails = data.get('emails', [])
                self.logger.info(f"Fetched {len(emails)} emails from queue")
                return emails
            else:
                self.logger.error(f"Failed to fetch queue: HTTP {response.status_code}")
                self.logger.error(f"Response: {response.text}")
                return []
                
        except requests.exceptions.RequestException as e:
            self.logger.error(f"Network error fetching queue: {e}")
            return []
        except Exception as e:
            self.logger.error(f"Error fetching queue: {e}")
            return []
            
    def download_attachment(self, attachment_url: str, filename: str) -> Optional[str]:
        """Download attachment from server"""
        try:
            url = f"{Config.SERVER_URL}{attachment_url}"
            response = self.session.get(url, timeout=60)
            
            if response.status_code == 200:
                # Save to temp directory
                temp_dir = Path(tempfile.gettempdir()) / 'email_engine'
                temp_dir.mkdir(exist_ok=True)
                
                file_path = temp_dir / filename
                with open(file_path, 'wb') as f:
                    f.write(response.content)
                    
                self.logger.debug(f"Downloaded attachment: {file_path}")
                return str(file_path)
            else:
                self.logger.error(f"Failed to download attachment: HTTP {response.status_code}")
                return None
                
        except Exception as e:
            self.logger.error(f"Error downloading attachment: {e}")
            return None
            
    def send_email(self, email_data: Dict) -> Dict:
        """Send single email via Outlook COM"""
        result = {
            'email_id': email_data.get('id'),
            'success': False,
            'message': '',
            'sent_at': None
        }
        
        try:
            # Create mail item
            mail = self.outlook.CreateItem(0)  # 0 = olMailItem
            
            # Set sender account
            sender_account = self.get_sender_account()
            if sender_account:
                mail.SendUsingAccount = sender_account
                
            # Set recipients
            to_emails = email_data.get('to_email', '')
            if isinstance(to_emails, list):
                to_emails = '; '.join(to_emails)
            mail.To = to_emails
            
            # Set CC
            cc_emails = email_data.get('cc_email', '')
            if cc_emails:
                if isinstance(cc_emails, list):
                    cc_emails = '; '.join(cc_emails)
                mail.CC = cc_emails
                
            # Set BCC
            bcc_emails = email_data.get('bcc_email', '')
            if bcc_emails:
                if isinstance(bcc_emails, list):
                    bcc_emails = '; '.join(bcc_emails)
                mail.BCC = bcc_emails
                
            # Set subject
            mail.Subject = email_data.get('subject', '')
            
            # Set body (HTML)
            body_html = email_data.get('body_html', '')
            if body_html:
                mail.HTMLBody = body_html
            else:
                mail.Body = email_data.get('body_text', '')
                
            # Set Reply-To
            reply_to = email_data.get('reply_to')
            if reply_to:
                mail.ReplyRecipients.Add(reply_to)
                
            # Handle attachments
            attachments_json = email_data.get('attachments_json')
            if attachments_json:
                try:
                    attachments = json.loads(attachments_json)
                    for attachment in attachments:
                        file_path = self.download_attachment(
                            attachment.get('url'), 
                            attachment.get('filename')
                        )
                        if file_path and os.path.exists(file_path):
                            mail.Attachments.Add(file_path)
                            self.logger.debug(f"Attached: {file_path}")
                except json.JSONDecodeError as e:
                    self.logger.error(f"Error parsing attachments: {e}")
                    
            # Send email
            mail.Send()
            
            result['success'] = True
            result['message'] = 'Email sent successfully'
            result['sent_at'] = datetime.now().isoformat()
            
            self.logger.info(f"Email sent successfully: {email_data.get('id')}")
            
            # Delay between sends
            if Config.SEND_DELAY_MS > 0:
                time.sleep(Config.SEND_DELAY_MS / 1000)
                
        except Exception as e:
            error_msg = str(e)
            result['message'] = f"Failed to send: {error_msg}"
            self.logger.error(f"Error sending email {email_data.get('id')}: {error_msg}")
            
        return result
        
    def report_status(self, results: List[Dict]):
        """Report email status back to server"""
        try:
            url = f"{Config.SERVER_URL}/api/engine/report-status"
            
            payload = {
                'engine_id': ENGINE_ID,
                'results': results
            }
            
            response = self.session.post(url, json=payload, timeout=30)
            
            if response.status_code == 200:
                self.logger.info(f"Status reported successfully: {len(results)} emails")
            else:
                self.logger.error(f"Failed to report status: HTTP {response.status_code}")
                
        except Exception as e:
            self.logger.error(f"Error reporting status: {e}")
            
    def run_once(self):
        """Run single iteration of email sending"""
        self.logger.info("Starting email processing cycle...")
        
        # Initialize Outlook if not already
        if not self.outlook:
            if not self.initialize_outlook():
                self.logger.error("Failed to initialize Outlook, aborting")
                return False
                
        # Fetch queue
        emails = self.fetch_email_queue()
        
        if not emails:
            self.logger.info("No emails to process")
            return True
            
        # Process emails
        results = []
        for email_data in emails:
            result = self.send_email(email_data)
            results.append(result)
            
        # Report status
        self.report_status(results)
        
        self.logger.info(f"Processed {len(emails)} emails")
        return True
        
    def run_daemon(self):
        """Run as daemon process"""
        self.running = True
        self.logger.info(f"Email Engine {VERSION} started (ID: {ENGINE_ID})")
        self.logger.info(f"Server: {Config.SERVER_URL}")
        self.logger.info(f"Check interval: {Config.AUTO_CHECK_INTERVAL_SEC} seconds")
        
        try:
            while self.running:
                success = self.run_once()
                
                if not success:
                    self.logger.warning("Cycle failed, retrying in next interval...")
                    
                # Wait before next check
                self.logger.info(f"Waiting {Config.AUTO_CHECK_INTERVAL_SEC} seconds...")
                time.sleep(Config.AUTO_CHECK_INTERVAL_SEC)
                
        except KeyboardInterrupt:
            self.logger.info("Received interrupt signal, shutting down...")
            self.running = False
        except Exception as e:
            self.logger.error(f"Unexpected error in daemon: {e}")
            
        self.logger.info("Email Engine stopped")
        
    def stop(self):
        """Stop the daemon"""
        self.running = False
        self.logger.info("Stop signal received")

def create_config_file(server_url: str, token: str, output_path: str):
    """Create configuration file for the engine"""
    config_content = f"""# ============================================
# Email Sender Engine Configuration
# Generated on: {datetime.now().isoformat()}
# ============================================

# ============================================
# SERVER CONFIGURATION (WAJIB)
# ============================================
# URL server web application Email Dispatcher
# Contoh local: http://localhost/email_dispatcher_suite
# Contoh production: https://email.company.com
# Contoh IP: http://192.168.1.100/email_dispatcher
EMAIL_ENGINE_SERVER={server_url}

# ============================================
# AUTHENTICATION (WAJIB)
# ============================================
# Token untuk autentikasi ke server
# Dapatkan token dari: Web App > Settings > Client Engine > Generate Token
EMAIL_ENGINE_TOKEN={token}

# ============================================
# OUTLOOK CONFIGURATION (Opsional)
# ============================================
# Email Outlook yang akan digunakan untuk mengirim
# Kosongkan untuk menggunakan default Outlook account
EMAIL_ENGINE_OUTLOOK_ACCOUNT=

# ============================================
# PERFORMANCE SETTINGS
# ============================================
# Delay antar pengiriman email (dalam milidetik)
# 1000 ms = 1 detik
EMAIL_ENGINE_DELAY=1000

# Jumlah email maksimum per batch
EMAIL_ENGINE_BATCH_SIZE=10

# Interval check antrian dari server (dalam detik)
# 60 = check setiap 1 menit
EMAIL_ENGINE_INTERVAL=60

# ============================================
# LOGGING
# ============================================
# Level logging: DEBUG, INFO, WARNING, ERROR
EMAIL_ENGINE_LOG_LEVEL=INFO

# ============================================
# CARA PENGGUNAAN:
# ============================================
# 1. Pastikan EMAIL_ENGINE_SERVER dan EMAIL_ENGINE_TOKEN sudah terisi
# 2. Simpan file ini
# 3. Jalankan dengan: EmailSenderEngine.exe --config {output_path} --daemon
#    atau: python email_sender_engine.py --config {output_path} --daemon
# ============================================
"""
    
    with open(output_path, 'w') as f:
        f.write(config_content)
    
    print("=" * 60)
    print(f"✅ Configuration file created: {output_path}")
    print("=" * 60)
    print()
    print("LANGKAH SELANJUTNYA:")
    print()
    print("1. Periksa konfigurasi di atas:")
    print(f"   - EMAIL_ENGINE_SERVER: {server_url}")
    print(f"   - EMAIL_ENGINE_TOKEN: {'*' * len(token)} (hidden)")
    print()
    print("2. Jalankan engine dengan:")
    print(f"   EmailSenderEngine.exe --config {output_path} --daemon")
    print("   atau")
    print(f"   python email_sender_engine.py --config {output_path} --daemon")
    print()
    print("=" * 60)

def main():
    parser = argparse.ArgumentParser(
        description='Email Sender Engine - Local Outlook Client',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Run once (single execution)
  python email_sender_engine.py --server http://server.com --token ABC123

  # Run as daemon (continuous)
  python email_sender_engine.py --server http://server.com --token ABC123 --daemon

  # Create config file only
  python email_sender_engine.py --server http://server.com --token ABC123 --create-config

  # Load from config file
  python email_sender_engine.py --config .env --daemon
        """
    )
    
    parser.add_argument('--server', '-s', help='Server URL (e.g., http://server.com)')
    parser.add_argument('--token', '-t', help='API Token for authentication')
    parser.add_argument('--daemon', '-d', action='store_true', help='Run as daemon')
    parser.add_argument('--config', '-c', help='Load configuration from file')
    parser.add_argument('--create-config', action='store_true', help='Create config file only')
    parser.add_argument('--outlook-account', help='Specific Outlook account to use')
    
    args = parser.parse_args()
    
    # Load from config file if specified
    if args.config:
        if os.path.exists(args.config):
            with open(args.config) as f:
                for line in f:
                    if '=' in line and not line.startswith('#'):
                        key, value = line.strip().split('=', 1)
                        os.environ[key] = value
            Config.load_from_env()
        else:
            print(f"Config file not found: {args.config}")
            sys.exit(1)
    else:
        # Use command line arguments
        if args.server:
            Config.SERVER_URL = args.server.rstrip('/')
        if args.token:
            Config.API_TOKEN = args.token
        if args.outlook_account:
            Config.OUTLOOK_ACCOUNT = args.outlook_account
            
    # Validate configuration
    if not Config.SERVER_URL or not Config.API_TOKEN:
        print("=" * 60)
        print("ERROR: Konfigurasi tidak lengkap!")
        print("=" * 60)
        print()
        print("Email Sender Engine membutuhkan konfigurasi berikut:")
        print()
        
        if not Config.SERVER_URL:
            print("❌ EMAIL_ENGINE_SERVER (URL server web application)")
            print("   Contoh: http://localhost/email_dispatcher_suite")
            print("   Contoh: https://email.company.com")
        else:
            print(f"✓ EMAIL_ENGINE_SERVER: {Config.SERVER_URL}")
            
        if not Config.API_TOKEN:
            print("❌ EMAIL_ENGINE_TOKEN (token autentikasi dari web app)")
            print("   Dapatkan token dari: Settings > Client Engine > Generate Token")
        else:
            print(f"✓ EMAIL_ENGINE_TOKEN: {'*' * len(Config.API_TOKEN)}")
        
        print()
        print("=" * 60)
        print("CARA MENGATASI:")
        print("=" * 60)
        print()
        print("Opsi 1: Command Line")
        print(f"  python email_sender_engine.py --server http://your-server.com --token YOUR_TOKEN")
        print()
        print("Opsi 2: Environment File (.env)")
        print("  1. Buat file .env di folder yang sama dengan script/exe")
        print("  2. Isi dengan:")
        print()
        print("     EMAIL_ENGINE_SERVER=http://your-server.com/email_dispatcher")
        print("     EMAIL_ENGINE_TOKEN=your-token-here")
        print("     EMAIL_ENGINE_DELAY=1000")
        print()
        print("  3. Jalankan: python email_sender_engine.py --config .env --daemon")
        print()
        print("=" * 60)
        print()
        parser.print_help()
        sys.exit(1)
        
    # Create config file only
    if args.create_config:
        if not Config.SERVER_URL or not Config.API_TOKEN:
            print("❌ Error: --server dan --token diperlukan untuk membuat config file")
            print()
            print("Contoh:")
            print("  python email_sender_engine.py --server http://server.com --token ABC123 --create-config")
            sys.exit(1)
        
        create_config_file(Config.SERVER_URL, Config.API_TOKEN, '.env')
        sys.exit(0)
        
    # Run engine
    engine = EmailSenderEngine()
    
    if args.daemon:
        engine.run_daemon()
    else:
        success = engine.run_once()
        sys.exit(0 if success else 1)

if __name__ == '__main__':
    main()
