<?php

require_once 'config.php';

/**
 * VisitorCounter class handles the process of counting and displaying unique website visitors.
 * It ensures atomic operations to handle concurrent accesses correctly and prevents image caching.
 */
class VisitorCounter {
    private $mysqli;
    private $ipAddress;
    private $currentTimestamp;
    private $userAgent;
    private $browserLanguage;
    private $headers;

    /**
     * Constructor initializes visitor details and database connection.
     */
    public function __construct() {
        $this->initializeVisitorDetails();
        $this->connectDatabase();
        $this->processVisitor();
    }

    /**
     * Initializes visitor-specific details from server variables.
     */
    private function initializeVisitorDetails() {
        $this->ipAddress = $_SERVER['REMOTE_ADDR'];
        $this->currentTimestamp = date('Y-m-d H:i:s');
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $this->browserLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $this->headers = json_encode(getallheaders());
    }

    /**
     * Establishes a database connection and sets the connection to use exceptions for error handling.
     */
    private function connectDatabase() {
        $this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $this->mysqli->connect_error);
        }
        $this->mysqli->autocommit(FALSE); // Disable autocommit to handle transactions manually.
    }

    /**
     * Checks if the visitor is new based on the IP address.
     *
     * @return bool True if the visitor is new, false otherwise.
     */
    private function isNewVisitor() {
        $checkQuery = $this->mysqli->prepare("SELECT visit_timestamp FROM visitor_ips WHERE ip_address = ?");
        $checkQuery->bind_param("s", $this->ipAddress);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        $checkQuery->close();

        return $result->num_rows === 0;
    }

    /**
     * Adds a new visitor's details to the database and increments the visitor count.
     */
    private function addNewVisitor() {
        $insertQuery = $this->mysqli->prepare("INSERT INTO visitor_ips (ip_address, visit_timestamp, user_agent, browser_language, headers) VALUES (?, ?, ?, ?, ?)");
        $insertQuery->bind_param("sssss", $this->ipAddress, $this->currentTimestamp, $this->userAgent, $this->browserLanguage, $this->headers);
        $insertQuery->execute();
        $insertQuery->close();
        $this->incrementVisitorCount();
    }

    /**
     * Updates the visit details for a returning visitor and increments the count if necessary.
     */
    private function updateExistingVisitor() {
        $selectQuery = $this->mysqli->prepare("SELECT visit_timestamp FROM visitor_ips WHERE ip_address = ?");
        $selectQuery->bind_param("s", $this->ipAddress);
        $selectQuery->execute();
        $result = $selectQuery->get_result();
        $row = $result->fetch_assoc();
        $selectQuery->close();

        $lastVisit = new DateTime($row['visit_timestamp']);
        $now = new DateTime($this->currentTimestamp);
        $interval = $lastVisit->diff($now);

        if ($interval->days >= RECOUNT_INTERVAL) {
            $updateQuery = $this->mysqli->prepare("UPDATE visitor_ips SET visit_timestamp = ?, user_agent = ?, browser_language = ?, headers = ? WHERE ip_address = ?");
            $updateQuery->bind_param("sssss", $this->currentTimestamp, $this->userAgent, $this->browserLanguage, $this->headers, $this->ipAddress);
            $updateQuery->execute();
            $updateQuery->close();
            $this->incrementVisitorCount();
        }
    }

    /**
     * Increments the visitor count within a transaction to ensure atomicity.
     */
    private function incrementVisitorCount() {
        $this->mysqli->begin_transaction();
        $updateCountQuery = $this->mysqli->prepare("UPDATE visitor_count SET count = count + 1");
        $updateCountQuery->execute();
        $updateCountQuery->close();
        $this->mysqli->commit();
    }

    /**
     * Retrieves the total visitor count, using a transaction to ensure the read is consistent.
     *
     * @return int The total count of unique visitors.
     */
    private function getVisitorCount() {
        $this->mysqli->begin_transaction();
        $selectCountQuery = $this->mysqli->prepare("SELECT count FROM visitor_count");
        $selectCountQuery->execute();
        $result = $selectCountQuery->get_result();
        $row = $result->fetch_assoc();
        $selectCountQuery->close();
        $this->mysqli->commit();
        return $row['count'];
    }

    /**
     * Public method to render the visitor count, displaying each digit as an image.
     * Appends a timestamp to the image URL to prevent caching.
     */
    public function renderCounter() {
        $count = $this->getVisitorCount();
        $numberStr = str_pad($count, VISITOR_COUNT_DIGITS, "0", STR_PAD_LEFT);
        $timestamp = time(); // Get the current timestamp to append to the image URL.

        foreach (str_split($numberStr) as $digit) {
            echo "<img src='counter/" . COUNTER_STYLE . "/{$digit}.png?{$timestamp}' alt='{$digit}' />";
        }
    }

    /**
     * Processes the visitor by determining if they are new or returning, and updates the database accordingly.
     */
    private function processVisitor() {
        if ($this->isNewVisitor()) {
            $this->addNewVisitor();
        } else {
            $this->updateExistingVisitor();
        }
    }

    /**
     * Destructor ensures the database connection is closed when the object is destroyed.
     */
    public function __destruct() {
        $this->mysqli->close();
    }
}

$visitorCounter = new VisitorCounter();
$visitorCounter->renderCounter();

?>
