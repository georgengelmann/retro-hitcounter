<?php

require_once 'config.php';

/**
 * VisitorCounter handles the process of counting unique visitors to a website.
 * It interacts with a database to track visitor information and counts,
 * ensuring unique visits are recorded and displayed.
 */
class VisitorCounter {
    private $mysqli;
    private $ipAddress;
    private $currentTimestamp;
    private $userAgent;
    private $browserLanguage;
    private $headers;

    /**
     * The constructor initializes the visitor tracking process.
     * It sets up visitor details and initiates the database connection and visitor processing.
     */
    public function __construct() {
        $this->ipAddress = $_SERVER['REMOTE_ADDR'];
        $this->currentTimestamp = date('Y-m-d H:i:s');
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $this->browserLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $this->headers = json_encode(getallheaders());
        $this->connectDatabase();
        $this->processVisitor();
    }

    /**
     * Establishes a connection to the database using credentials from the config file.
     */
    private function connectDatabase() {
        $this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->mysqli->connect_error) {
            die("Connection failed: " . $this->mysqli->connect_error);
        }
    }

    /**
     * Checks if the current visitor's IP address is already recorded in the database.
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
     * Inserts a new visitor's details into the database and increments the visitor count.
     */
    private function addNewVisitor() {
        $insertQuery = $this->mysqli->prepare("INSERT INTO visitor_ips (ip_address, visit_timestamp, user_agent, browser_language, headers) VALUES (?, ?, ?, ?, ?)");
        $insertQuery->bind_param("sssss", $this->ipAddress, $this->currentTimestamp, $this->userAgent, $this->browserLanguage, $this->headers);
        $insertQuery->execute();
        $insertQuery->close();
        $this->incrementVisitorCount();
    }

    /**
     * Updates the record of an existing visitor if the visit is beyond the RECOUNT_INTERVAL.
     * This ensures that the same visitor is not counted more than once within the interval.
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
     * Increments the total count of unique visitors in the database.
     */
    private function incrementVisitorCount() {
        $updateCountQuery = $this->mysqli->prepare("UPDATE visitor_count SET count = count + 1");
        $updateCountQuery->execute();
        $updateCountQuery->close();
    }

    /**
     * Retrieves the total count of unique visitors from the database.
     * 
     * @return int The total count of unique visitors.
     */
    private function getVisitorCount() {
        $selectCountQuery = $this->mysqli->prepare("SELECT count FROM visitor_count");
        $selectCountQuery->execute();
        $result = $selectCountQuery->get_result();
        $row = $result->fetch_assoc();
        $selectCountQuery->close();
        return $row['count'];
    }

    /**
     * Renders the visitor count as a series of images, one for each digit of the count.
     * 
     * @param int $count The visitor count to render.
     */
    private function renderCounter($count) {
        $numberStr = str_pad($count, VISITOR_COUNT_DIGITS, "0", STR_PAD_LEFT);
        foreach (str_split($numberStr) as $digit) {
            echo "<img src='counter/" . COUNTER_STYLE . "/{$digit}.png' alt='{$digit}' />";
        }
    }

    /**
     * Determines the visitor's uniqueness and processes their visit.
     * It adds a new visitor or updates an existing visitor's record and renders the visitor count.
     */
    private function processVisitor() {
        if ($this->isNewVisitor()) {
            $this->addNewVisitor();
        } else {
            $this->updateExistingVisitor();
        }
        $count = $this->getVisitorCount();
        $this->renderCounter($count);
    }

    /**
     * Ensures the database connection is closed when the object is destroyed.
     */
    public function __destruct() {
        $this->mysqli->close();
    }
}

new VisitorCounter();

?>
