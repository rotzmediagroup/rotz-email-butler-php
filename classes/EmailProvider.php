<?php
/**
 * ROTZ Email Butler - Email Provider Class
 * Handles connections to all major email providers
 */

namespace Rotz\EmailButler\Classes;

use Exception;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Connection;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\Google;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class EmailProvider {
    private $db;
    private $httpClient;
    
    // Email provider configurations
    private $providerConfigs = [
        'gmail' => [
            'name' => 'Gmail',
            'imap_server' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => true,
            'app_password_supported' => true
        ],
        'outlook' => [
            'name' => 'Outlook.com / Hotmail',
            'imap_server' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp-mail.outlook.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => true,
            'app_password_supported' => true
        ],
        'office365' => [
            'name' => 'Office 365 / Exchange Online',
            'imap_server' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.office365.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => true,
            'app_password_supported' => true
        ],
        'yahoo' => [
            'name' => 'Yahoo Mail',
            'imap_server' => 'imap.mail.yahoo.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.mail.yahoo.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'icloud' => [
            'name' => 'iCloud Mail',
            'imap_server' => 'imap.mail.me.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.mail.me.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'protonmail' => [
            'name' => 'ProtonMail',
            'imap_server' => '127.0.0.1',
            'imap_port' => 1143,
            'imap_encryption' => 'none',
            'smtp_server' => '127.0.0.1',
            'smtp_port' => 1025,
            'smtp_encryption' => 'none',
            'oauth_supported' => false,
            'app_password_supported' => false,
            'requires_bridge' => true,
            'api_supported' => true
        ],
        'tutanota' => [
            'name' => 'Tutanota',
            'imap_server' => null,
            'imap_port' => null,
            'imap_encryption' => null,
            'smtp_server' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'oauth_supported' => false,
            'app_password_supported' => false,
            'api_only' => true,
            'api_endpoint' => 'https://mail.tutanota.com/rest'
        ],
        'fastmail' => [
            'name' => 'Fastmail',
            'imap_server' => 'imap.fastmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.fastmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'zoho' => [
            'name' => 'Zoho Mail',
            'imap_server' => 'imap.zoho.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.zoho.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => true,
            'app_password_supported' => true
        ],
        'aol' => [
            'name' => 'AOL Mail',
            'imap_server' => 'imap.aol.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.aol.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'yandex' => [
            'name' => 'Yandex Mail',
            'imap_server' => 'imap.yandex.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.yandex.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'gmx' => [
            'name' => 'GMX Mail',
            'imap_server' => 'imap.gmx.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.gmx.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'mail_com' => [
            'name' => 'Mail.com',
            'imap_server' => 'imap.mail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_server' => 'smtp.mail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'oauth_supported' => false,
            'app_password_supported' => true
        ],
        'mailgun' => [
            'name' => 'Mailgun (API)',
            'api_endpoint' => 'https://api.mailgun.net/v3',
            'api_only' => true,
            'sending_only' => true
        ],
        'sendgrid' => [
            'name' => 'SendGrid (API)',
            'api_endpoint' => 'https://api.sendgrid.com/v3',
            'api_only' => true,
            'sending_only' => true
        ],
        'amazon_ses' => [
            'name' => 'Amazon SES (API)',
            'api_endpoint' => 'https://email.{region}.amazonaws.com',
            'api_only' => true,
            'sending_only' => true
        ],
        'postmark' => [
            'name' => 'Postmark (API)',
            'api_endpoint' => 'https://api.postmarkapp.com',
            'api_only' => true,
            'sending_only' => true
        ],
        'mandrill' => [
            'name' => 'Mandrill (API)',
            'api_endpoint' => 'https://mandrillapp.com/api/1.0',
            'api_only' => true,
            'sending_only' => true
        ],
        'sparkpost' => [
            'name' => 'SparkPost (API)',
            'api_endpoint' => 'https://api.sparkpost.com/api/v1',
            'api_only' => true,
            'sending_only' => true
        ],
        'custom_imap' => [
            'name' => 'Custom IMAP',
            'custom_config' => true
        ],
        'custom_exchange' => [
            'name' => 'Custom Exchange',
            'custom_config' => true,
            'exchange_server' => true
        ],
        'custom_api' => [
            'name' => 'Custom API',
            'custom_config' => true,
            'api_only' => true
        ]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->httpClient = new Client(['timeout' => 30]);
    }

    /**
     * Get all available email providers
     */
    public function getAvailableProviders() {
        return $this->providerConfigs;
    }

    /**
     * Test email provider connection
     */
    public function testConnection($providerId) {
        $sql = "SELECT * FROM email_providers WHERE id = ?";
        $provider = $this->db->fetchOne($sql, [$providerId]);
        
        if (!$provider) {
            throw new Exception('Provider not found');
        }

        $config = $this->providerConfigs[$provider['provider_type']] ?? null;
        if (!$config) {
            throw new Exception('Unknown provider type');
        }

        try {
            if (isset($config['api_only']) && $config['api_only']) {
                return $this->testApiConnection($provider, $config);
            } else {
                return $this->testImapConnection($provider, $config);
            }
        } catch (Exception $e) {
            // Update provider status
            $this->db->update('email_providers', 
                ['status' => 'error', 'last_error' => $e->getMessage()], 
                'id = ?', 
                [$providerId]
            );
            throw $e;
        }
    }

    /**
     * Test IMAP connection
     */
    private function testImapConnection($provider, $config) {
        $server = new Server(
            $provider['imap_server'] ?: $config['imap_server'],
            $provider['imap_port'] ?: $config['imap_port'],
            $provider['imap_encryption'] ?: $config['imap_encryption']
        );

        $username = $provider['username'];
        $password = $this->db->decrypt($provider['password_encrypted']);

        // Test IMAP connection
        $connection = $server->authenticate($username, $password);
        
        // Test basic operations
        $mailboxes = $connection->getMailboxes();
        $inbox = $connection->getMailbox('INBOX');
        $messageCount = count($inbox->getMessages());

        $connection->close();

        // Update provider status
        $this->db->update('email_providers', 
            ['status' => 'active', 'last_error' => null, 'last_sync' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$provider['id']]
        );

        return [
            'success' => true,
            'message' => 'IMAP connection successful',
            'mailbox_count' => count($mailboxes),
            'message_count' => $messageCount
        ];
    }

    /**
     * Test API connection
     */
    private function testApiConnection($provider, $config) {
        $apiKey = $this->db->decrypt($provider['api_key_encrypted']);
        
        switch ($provider['provider_type']) {
            case 'mailgun':
                return $this->testMailgunConnection($apiKey, $config);
            case 'sendgrid':
                return $this->testSendGridConnection($apiKey, $config);
            case 'postmark':
                return $this->testPostmarkConnection($apiKey, $config);
            case 'tutanota':
                return $this->testTutanotaConnection($apiKey, $config);
            default:
                throw new Exception('API testing not implemented for this provider');
        }
    }

    /**
     * Sync emails from provider
     */
    public function syncEmails($providerId, $limit = 50) {
        $sql = "SELECT * FROM email_providers WHERE id = ? AND status = 'active'";
        $provider = $this->db->fetchOne($sql, [$providerId]);
        
        if (!$provider) {
            throw new Exception('Provider not found or inactive');
        }

        $config = $this->providerConfigs[$provider['provider_type']] ?? null;
        if (!$config) {
            throw new Exception('Unknown provider type');
        }

        if (isset($config['api_only']) && $config['api_only']) {
            return $this->syncEmailsViaApi($provider, $config, $limit);
        } else {
            return $this->syncEmailsViaImap($provider, $config, $limit);
        }
    }

    /**
     * Sync emails via IMAP
     */
    private function syncEmailsViaImap($provider, $config, $limit) {
        $server = new Server(
            $provider['imap_server'] ?: $config['imap_server'],
            $provider['imap_port'] ?: $config['imap_port'],
            $provider['imap_encryption'] ?: $config['imap_encryption']
        );

        $username = $provider['username'];
        $password = $this->db->decrypt($provider['password_encrypted']);

        $connection = $server->authenticate($username, $password);
        $inbox = $connection->getMailbox('INBOX');
        
        // Get recent messages
        $messages = $inbox->getMessages(null, \SORTDATE, true, null, null, $limit);
        $syncedCount = 0;
        $newEmails = [];

        foreach ($messages as $message) {
            try {
                $messageId = $message->getId();
                
                // Check if email already exists
                $existingSql = "SELECT id FROM emails WHERE message_id = ? AND provider_id = ?";
                $existing = $this->db->fetchOne($existingSql, [$messageId, $provider['id']]);
                
                if ($existing) {
                    continue; // Skip existing emails
                }

                // Extract email data
                $emailData = [
                    'provider_id' => $provider['id'],
                    'message_id' => $messageId,
                    'thread_id' => $message->getThread() ?? null,
                    'sender_email' => $message->getFrom()->getAddress(),
                    'sender_name' => $message->getFrom()->getName(),
                    'recipient_email' => $provider['email_address'],
                    'subject' => $message->getSubject(),
                    'body_text' => $message->getBodyText(),
                    'body_html' => $message->getBodyHtml(),
                    'received_at' => $message->getDate()->format('Y-m-d H:i:s'),
                    'is_read' => $message->isSeen(),
                    'is_starred' => $message->isFlagged(),
                    'has_attachments' => $message->hasAttachments(),
                    'attachment_count' => count($message->getAttachments()),
                    'processing_status' => 'pending'
                ];

                // Insert email
                $emailId = $this->db->insert('emails', $emailData);
                $emailData['id'] = $emailId;
                $newEmails[] = $emailData;

                // Handle attachments
                if ($message->hasAttachments()) {
                    $this->saveAttachments($emailId, $message->getAttachments());
                }

                $syncedCount++;

            } catch (Exception $e) {
                error_log("Error syncing email {$messageId}: " . $e->getMessage());
                continue;
            }
        }

        $connection->close();

        // Update provider sync status
        $this->db->update('email_providers', 
            [
                'last_sync' => date('Y-m-d H:i:s'),
                'total_emails' => $provider['total_emails'] + $syncedCount
            ], 
            'id = ?', 
            [$provider['id']]
        );

        return [
            'success' => true,
            'synced_count' => $syncedCount,
            'new_emails' => $newEmails
        ];
    }

    /**
     * Sync emails via API (for API-only providers)
     */
    private function syncEmailsViaApi($provider, $config, $limit) {
        switch ($provider['provider_type']) {
            case 'tutanota':
                return $this->syncTutanotaEmails($provider, $config, $limit);
            default:
                throw new Exception('API sync not implemented for this provider');
        }
    }

    /**
     * Send email via provider
     */
    public function sendEmail($providerId, $emailData) {
        $sql = "SELECT * FROM email_providers WHERE id = ? AND status = 'active'";
        $provider = $this->db->fetchOne($sql, [$providerId]);
        
        if (!$provider) {
            throw new Exception('Provider not found or inactive');
        }

        $config = $this->providerConfigs[$provider['provider_type']] ?? null;
        if (!$config) {
            throw new Exception('Unknown provider type');
        }

        if (isset($config['api_only']) && $config['api_only']) {
            return $this->sendEmailViaApi($provider, $config, $emailData);
        } else {
            return $this->sendEmailViaSmtp($provider, $config, $emailData);
        }
    }

    /**
     * Send email via SMTP
     */
    private function sendEmailViaSmtp($provider, $config, $emailData) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $provider['smtp_server'] ?: $config['smtp_server'];
            $mail->SMTPAuth = true;
            $mail->Username = $provider['username'];
            $mail->Password = $this->db->decrypt($provider['password_encrypted']);
            $mail->SMTPSecure = $provider['smtp_encryption'] ?: $config['smtp_encryption'];
            $mail->Port = $provider['smtp_port'] ?: $config['smtp_port'];

            // Recipients
            $mail->setFrom($provider['email_address'], $provider['display_name']);
            $mail->addAddress($emailData['to_email']);
            
            if (!empty($emailData['cc_email'])) {
                foreach (explode(',', $emailData['cc_email']) as $cc) {
                    $mail->addCC(trim($cc));
                }
            }
            
            if (!empty($emailData['bcc_email'])) {
                foreach (explode(',', $emailData['bcc_email']) as $bcc) {
                    $mail->addBCC(trim($bcc));
                }
            }

            // Content
            $mail->isHTML(!empty($emailData['body_html']));
            $mail->Subject = $emailData['subject'];
            $mail->Body = $emailData['body_html'] ?: $emailData['body_text'];
            
            if (!empty($emailData['body_html']) && !empty($emailData['body_text'])) {
                $mail->AltBody = $emailData['body_text'];
            }

            $mail->send();

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => $mail->getLastMessageID()
            ];

        } catch (Exception $e) {
            throw new Exception('Email sending failed: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Send email via API
     */
    private function sendEmailViaApi($provider, $config, $emailData) {
        $apiKey = $this->db->decrypt($provider['api_key_encrypted']);
        
        switch ($provider['provider_type']) {
            case 'mailgun':
                return $this->sendViaMailgun($apiKey, $config, $emailData);
            case 'sendgrid':
                return $this->sendViaSendGrid($apiKey, $config, $emailData);
            case 'postmark':
                return $this->sendViaPostmark($apiKey, $config, $emailData);
            default:
                throw new Exception('API sending not implemented for this provider');
        }
    }

    /**
     * Save email attachments
     */
    private function saveAttachments($emailId, $attachments) {
        $attachmentDir = __DIR__ . '/../storage/attachments/' . date('Y/m/d');
        
        if (!is_dir($attachmentDir)) {
            mkdir($attachmentDir, 0755, true);
        }

        foreach ($attachments as $attachment) {
            try {
                $filename = $attachment->getFilename();
                $content = $attachment->getDecodedContent();
                $filePath = $attachmentDir . '/' . uniqid() . '_' . $filename;
                
                file_put_contents($filePath, $content);

                $attachmentData = [
                    'email_id' => $emailId,
                    'filename' => $filename,
                    'content_type' => $attachment->getType(),
                    'size' => strlen($content),
                    'file_path' => $filePath,
                    'is_inline' => $attachment->isInline(),
                    'content_id' => $attachment->getId()
                ];

                $this->db->insert('email_attachments', $attachmentData);

            } catch (Exception $e) {
                error_log("Error saving attachment: " . $e->getMessage());
            }
        }
    }

    /**
     * Mailgun API methods
     */
    private function testMailgunConnection($apiKey, $config) {
        $response = $this->httpClient->get($config['api_endpoint'] . '/domains', [
            'auth' => ['api', $apiKey]
        ]);

        if ($response->getStatusCode() === 200) {
            return [
                'success' => true,
                'message' => 'Mailgun API connection successful'
            ];
        }

        throw new Exception('Mailgun API connection failed');
    }

    private function sendViaMailgun($apiKey, $config, $emailData) {
        // Implementation for Mailgun sending
        $domain = 'your-domain.com'; // This should be configurable
        
        $response = $this->httpClient->post($config['api_endpoint'] . "/{$domain}/messages", [
            'auth' => ['api', $apiKey],
            'form_params' => [
                'from' => $emailData['from_email'],
                'to' => $emailData['to_email'],
                'subject' => $emailData['subject'],
                'text' => $emailData['body_text'],
                'html' => $emailData['body_html']
            ]
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        
        return [
            'success' => true,
            'message' => 'Email sent via Mailgun',
            'message_id' => $result['id']
        ];
    }

    /**
     * SendGrid API methods
     */
    private function testSendGridConnection($apiKey, $config) {
        $response = $this->httpClient->get($config['api_endpoint'] . '/user/profile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            return [
                'success' => true,
                'message' => 'SendGrid API connection successful'
            ];
        }

        throw new Exception('SendGrid API connection failed');
    }

    /**
     * Postmark API methods
     */
    private function testPostmarkConnection($apiKey, $config) {
        $response = $this->httpClient->get($config['api_endpoint'] . '/server', [
            'headers' => [
                'X-Postmark-Server-Token' => $apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            return [
                'success' => true,
                'message' => 'Postmark API connection successful'
            ];
        }

        throw new Exception('Postmark API connection failed');
    }

    /**
     * Tutanota API methods
     */
    private function testTutanotaConnection($apiKey, $config) {
        // Tutanota API implementation would go here
        return [
            'success' => true,
            'message' => 'Tutanota API connection test not implemented'
        ];
    }

    private function syncTutanotaEmails($provider, $config, $limit) {
        // Tutanota email sync implementation would go here
        return [
            'success' => true,
            'synced_count' => 0,
            'new_emails' => []
        ];
    }
}
?>

