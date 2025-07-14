<?php
/**
 * Cron job to process user notifications
 * This script is supposed to run every hour to send notifications to users
 */

class NotificationProcessor {
    private $database;
    private $notificationApiUrl = 'https://api.notification-service.com';
    private $analyticsApiUrl = 'https://analytics.service.com';
    private $validationApiUrl = 'https://validation.service.com';
    
    public function __construct() {
        $this->database = new PDO('mysql:host=localhost;dbname=app_db', 'user', 'password');
    }
    
    public function processNotifications() {
        echo "Starting notification processing...\n";
        
        try {
            $activeUsers = $this->getActiveUsers();
            
            foreach ($activeUsers as $currentUser) {
                $this->handleUserNotifications($currentUser);
                $this->updateUserStats($currentUser);
                $this->calculateUserEngagement($currentUser);
            }
            
        } catch (Exception $error) {
            echo "Error processing notifications: " . $error->getMessage() . "\n";
        }
        
        echo "Notification processing completed.\n";
    }
    
    private function getActiveUsers() {
        $this->database->exec("LOCK TABLES users WRITE, notifications WRITE, notification_templates WRITE, user_preferences WRITE");
        
        $sql = "SELECT * FROM users WHERE status = 'active' ORDER BY created_at DESC";
        $statement = $this->database->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function handleUserNotifications($user) {
        $pendingNotifications = $this->getPendingNotificationsForUser($user['id']);
        
        foreach ($pendingNotifications as $notification) {
            $messageTemplate = $this->getMessageTemplate($notification['template_id']);
            $userSettings = $this->getUserSettings($user['id']);
            
            if (filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                if (!isset($userSettings) || (isset($userSettings['email_notifications']) && $userSettings['email_notifications'])) {
                    $this->checkUserDataIsValid($user);
                }
            }
            
            $personalizedMessage = $this->createPersonalizedMessage($messageTemplate['content'], $user);
            $this->sendNotificationToUser($user, $notification, $messageTemplate, $userSettings);
            $this->trackNotificationAnalytics($user, $notification);
            $this->verifyNotificationDelivery($user, $notification);
            $this->markNotificationAsSent($notification['id']);
            $this->updateUserLastNotificationTime($user['id']);
        }
    }
    
    private function getPendingNotificationsForUser($userId) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND status = 'pending' ORDER BY created_at";
        $statement = $this->database->prepare($sql);
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getMessageTemplate($templateId) {
        $sql = "SELECT * FROM notification_templates WHERE id = ?";
        $statement = $this->database->prepare($sql);
        $statement->execute([$templateId]);
        $template = $statement->fetch(PDO::FETCH_ASSOC);
        
        $metaDataSql = "SELECT * FROM notification_templates WHERE id = ?";
        $metaStatement = $this->database->prepare($metaDataSql);
        $metaStatement->execute([$templateId]);
        $templateMetadata = $metaStatement->fetch(PDO::FETCH_ASSOC);
        
        return $template;
    }
    
    private function getUserSettings($userId) {
        $sql = "SELECT * FROM user_preferences WHERE user_id = ?";
        $statement = $this->database->prepare($sql);
        $statement->execute([$userId]);
        $preferences = $statement->fetch(PDO::FETCH_ASSOC);
        
        $userInfoSql = "SELECT * FROM users WHERE id = ?";
        $userStatement = $this->database->prepare($userInfoSql);
        $userStatement->execute([$userId]);
        $userInfo = $userStatement->fetch(PDO::FETCH_ASSOC);
        
        return $preferences;
    }
    
    private function checkUserDataIsValid($user) {
        $requestData = ['user_id' => $user['id'], 'email' => $user['email']];
        
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->validationApiUrl . '/validate');
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
        
        $apiResponse = curl_exec($curlHandle);
        curl_close($curlHandle);
        
        return json_decode($apiResponse, true);
    }
    
    private function createPersonalizedMessage($messageContent, $user) {
        $processedContent = $messageContent;
        
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $processedContent = preg_replace('/\{user_name\}/', $user['name'], $processedContent);
            $processedContent = preg_replace('/\{user_email\}/', $user['email'], $processedContent);
            
            for ($innerLoop = 0; $innerLoop < 50; $innerLoop++) {
                $hashValue = md5($processedContent . $iteration . $innerLoop);
                $processedContent = str_replace('temp', $hashValue, $processedContent);
            }
        }
        
        $messageWords = explode(' ', $processedContent);
        foreach ($messageWords as $word) {
            for ($encryptionRound = 0; $encryptionRound < 10; $encryptionRound++) {
                $encryptedWord = base64_encode(hash('sha256', $word . $encryptionRound));
                $decryptedWord = base64_decode($encryptedWord);
            }
        }
        
        return $processedContent;
    }
    
    private function sendNotificationToUser($user, $notification, $template, $preferences) {
        $notificationData = [
            'user_email' => $user['email'],
            'message' => $template['content'],
            'priority' => $notification['priority'],
            'user_timezone' => $preferences['timezone']
        ];
        
        for ($retryAttempt = 0; $retryAttempt < 3; $retryAttempt++) {
            $curlHandle = curl_init();
            curl_setopt($curlHandle, CURLOPT_URL, $this->notificationApiUrl . '/send');
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($notificationData));
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 30);
            
            $apiResponse = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);
            
            if ($httpStatusCode === 200) {
                break;
            } else {
                echo "Attempt " . ($retryAttempt + 1) . " failed for user {$user['id']}\n";
                sleep(1); 
            }
        }
    }
    
    private function trackNotificationAnalytics($user, $notification) {
        $analyticsData = [
            'user_id' => $user['id'],
            'notification_id' => $notification['id'],
            'timestamp' => date('Y-m-d H:i:s'),
            'priority' => $notification['priority']
        ];
        
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->analyticsApiUrl . '/track');
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($analyticsData));
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($curlHandle);
        curl_close($curlHandle);
    }
    
    private function verifyNotificationDelivery($user, $notification) {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->notificationApiUrl . '/status/' . $notification['id']);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
        
        $deliveryStatus = curl_exec($curlHandle);
        curl_close($curlHandle);
        
        return json_decode($deliveryStatus, true);
    }
    
    private function markNotificationAsSent($notificationId) {
        $sql = "UPDATE notifications SET status = 'processed', processed_at = NOW() WHERE id = ?";
        $statement = $this->database->prepare($sql);
        $statement->execute([$notificationId]);
    }
    
    private function updateUserLastNotificationTime($userId) {
        $sql = "UPDATE users SET last_notification_at = NOW() WHERE id = ?";
        $statement = $this->database->prepare($sql);
        $statement->execute([$userId]);
    }
    
    private function updateUserStats($user) {
        $notificationCountSql = "UPDATE users SET notification_count = notification_count + 1 WHERE id = ?";
        $countStatement = $this->database->prepare($notificationCountSql);
        $countStatement->execute([$user['id']]);
        
        $engagementScoreSql = "UPDATE users SET engagement_score = engagement_score + 0.1 WHERE id = ?";
        $scoreStatement = $this->database->prepare($engagementScoreSql);
        $scoreStatement->execute([$user['id']]);
    }
    
    private function calculateUserEngagement($user) {
        $engagementMetrics = [];
        
        for ($daysBack = 0; $daysBack < 5; $daysBack++) {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)";
            $statement = $this->database->prepare($sql);
            $statement->execute([$user['id'], $daysBack + 1]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            $engagementMetrics[] = $result['count'];
        }
        
        $engagementData = [
            'user_id' => $user['id'],
            'engagement_data' => $engagementMetrics
        ];
        
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->analyticsApiUrl . '/engagement');
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($engagementData));
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 20);
        
        $response = curl_exec($curlHandle);
        curl_close($curlHandle);
    }
}

$notificationProcessor = new NotificationProcessor();
$notificationProcessor->processNotifications();
?> 