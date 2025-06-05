<?php
require_once 'config.php';

class UserManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function addUser($username, $password, $role, $full_name) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, password, role, full_name)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$username, $hashed_password, $role, $full_name]);
    }
    
    public function updateUser($user_id, $data) {
        $sql = "UPDATE users SET ";
        $params = [];
        $updates = [];
        
        if (isset($data['username'])) {
            $updates[] = "username = ?";
            $params[] = $data['username'];
        }
        
        if (isset($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['role'])) {
            $updates[] = "role = ?";
            $params[] = $data['role'];
        }
        
        if (isset($data['full_name'])) {
            $updates[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql .= implode(", ", $updates) . " WHERE user_id = ?";
        $params[] = $user_id;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function getUsers() {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY username");
        return $stmt->fetchAll();
    }
    
    public function getUser($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    public function deleteUser($user_id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }
}
?>