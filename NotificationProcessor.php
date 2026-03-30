<?php
/**
 * Notification Processing System
 * 
 * CONTEXT:
 * This system processes pending notifications and sends them to users via external APIs.
 * It currently runs as a scheduled job, but the team wants to improve throughput and reliability.
 * 
 * YOUR TASK:
 * The system has performance and reliability issues under load.
 * Analyze the code, identify critical problems, and implement or design solutions.
 * 
 * You won't have time to fix everything - focus on what matters most.
 * Use comments to explain your reasoning and outline approaches you don't have time to implement.
 */

class NotificationProcessor
{
    private $db;
    private $apiUrl = 'https://api.notification-service.com';

    public function __construct()
    {
        $this->db = new PDO('mysql:host=localhost;dbname=app_db', 'user', 'password');
    }

    public function process()
    {
        echo "Starting notification processing...\n";

        $notifications = $this->getPendingNotifications();

        foreach ($notifications as $notification) {
            $user = $this->getUserById($notification['user_id']);
            $template = $this->getTemplate($notification['template_id']);

            $message = $this->buildMessage($template['content'], $user);

            $this->sendNotification($user['email'], $message, $notification);

            $this->updateNotificationStatus($notification['id'], 'sent');

            $this->trackAnalytics($notification);
        }

        echo "Processing completed.\n";
    }

    private function getPendingNotifications()
    {
        $sql = "SELECT * FROM notifications WHERE status = 'pending' ORDER BY created_at LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUserById($userId)
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getTemplate($templateId)
    {
        $sql = "SELECT * FROM notification_templates WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function buildMessage($template, $user)
    {
        $message = $template;
        $message = str_replace('{user_name}', $user['name'], $message);
        $message = str_replace('{user_email}', $user['email'], $message);
        return $message;
    }

    private function sendNotification($email, $message, $notification)
    {
        $data = [
            'email' => $email,
            'message' => $message,
            'priority' => $notification['priority']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "Failed to send notification {$notification['id']}\n";
        }
    }

    private function updateNotificationStatus($id, $status)
    {
        $sql = "UPDATE notifications SET status = ?, sent_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $id]);
    }


    private function trackAnalytics($notification)
    {
        $data = [
            'notification_id' => $notification['id'],
            'user_id' => $notification['user_id'],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://analytics.service.com/track');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_exec($ch);
        curl_close($ch);
    }
}

// Run processor
$processor = new NotificationProcessor();
$processor->process();
?>