<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
use Exception;

/**
 * DailySalesStat Model
 * Handles database operations for daily sales statistics.
 */
class DailySalesStat extends Model
{
    protected string $table = 'daily_sales_stats';
    
    /**
     * Fetches a single daily stat by its date.
     * @param string $date The date of the stat.
     * @return array|null Stat data if found, null otherwise.
     */
    public function findByDate(string $date): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE date = :date");
            $stmt->bindParam(':date', $date, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching daily stat by date: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Fetches daily stats within a date range.
     * @param string $dateFrom Start date.
     * @param string $dateTo End date.
     * @return array List of daily stats.
     */
    public function getStatsByDateRange(string $dateFrom, string $dateTo): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE date BETWEEN :date_from AND :date_to ORDER BY date ASC");
            $stmt->bindParam(':date_from', $dateFrom);
            $stmt->bindParam(':date_to', $dateTo);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching stats by date range: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Creates or updates a daily stat.
     * @param array $data Associative array of stat data.
     * @return bool True on success, false on failure.
     */
    public function save(array $data): bool
    {
        // Validate required fields
        if (empty($data['date'])) {
            return false;
        }
        
        // Check if stat exists
        $existing = $this->findByDate($data['date']);
        
        if ($existing) {
            // Update existing stat
            return $this->update($existing['id'], $data);
        } else {
            // Create new stat
            return $this->create($data) > 0;
        }
    }
    
    /**
     * Creates a new daily stat.
     * @param array $data Associative array of stat data.
     * @return int|false Inserted ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        $query = "INSERT INTO {$this->table} 
                 (date, orders_count, revenue, new_customers, returning_customers, products_sold, avg_order_value) 
                 VALUES 
                 (:date, :orders_count, :revenue, :new_customers, :returning_customers, :products_sold, :avg_order_value)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':date', $data['date']);
            $stmt->bindValue(':orders_count', $data['orders_count'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':revenue', $data['revenue'] ?? 0);
            $stmt->bindValue(':new_customers', $data['new_customers'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':returning_customers', $data['returning_customers'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':products_sold', $data['products_sold'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':avg_order_value', $data['avg_order_value'] ?? 0);
            
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating daily stat: " . $e->getMessage());
            return false;
        }   
    }
    
    /**
     * Updates an existing daily stat.
     * @param mixed $id The ID of the stat to update.
     * @param array $data Associative array of stat data.
     * @return bool True on success, false on failure.
     */
    public function update($id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];
        
        foreach($data as $key => $value) {
            if (in_array($key, ['date', 'orders_count', 'revenue', 'new_customers', 'returning_customers', 'products_sold', 'avg_order_value'])) {
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (empty($setClauses)) {
            return false; // No fields to update
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $val) {
                $paramType = PDO::PARAM_STR;
                if (is_int($val)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_float($val)) {
                    $paramType = PDO::PARAM_STR; // PDO doesn't have PARAM_FLOAT
                } elseif (is_bool($val)) {
                    $paramType = PDO::PARAM_BOOL;
                }
                $stmt->bindValue($key, $val, $paramType);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating daily stat: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gets monthly sales statistics.
     * @param int $year The year.
     * @return array Monthly sales statistics.
     */
    public function getMonthlyStats(int $year): array
    {
        try {
            $query = "
                SELECT MONTH(date) as month, 
                       SUM(orders_count) as orders_count, 
                       SUM(revenue) as revenue, 
                       SUM(new_customers) as new_customers, 
                       SUM(returning_customers) as returning_customers, 
                       SUM(products_sold) as products_sold, 
                       AVG(avg_order_value) as avg_order_value 
                FROM {$this->table} 
                WHERE YEAR(date) = :year 
                GROUP BY MONTH(date) 
                ORDER BY month
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize all months with zero values
            $monthlyStats = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyStats[$month - 1] = [
                    'month' => $month,
                    'orders_count' => 0,
                    'revenue' => 0,
                    'new_customers' => 0,
                    'returning_customers' => 0,
                    'products_sold' => 0,
                    'avg_order_value' => 0
                ];
            }
            
            // Fill with actual data
            foreach ($results as $result) {
                $monthIndex = $result['month'] - 1;
                $monthlyStats[$monthIndex] = $result;
            }
            
            return $monthlyStats;
        } catch (PDOException $e) {
            error_log("Error fetching monthly stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets yearly sales statistics.
     * @param int $startYear The start year.
     * @param int $endYear The end year.
     * @return array Yearly sales statistics.
     */
    public function getYearlyStats(int $startYear, int $endYear): array
    {
        try {
            $query = "
                SELECT YEAR(date) as year, 
                       SUM(orders_count) as orders_count, 
                       SUM(revenue) as revenue, 
                       SUM(new_customers) as new_customers, 
                       SUM(returning_customers) as returning_customers, 
                       SUM(products_sold) as products_sold, 
                       AVG(avg_order_value) as avg_order_value 
                FROM {$this->table} 
                WHERE YEAR(date) BETWEEN :start_year AND :end_year 
                GROUP BY YEAR(date) 
                ORDER BY year
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':start_year', $startYear, PDO::PARAM_INT);
            $stmt->bindParam(':end_year', $endYear, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching yearly stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generates daily stats from orders data.
     * @param string $date The date to generate stats for.
     * @return bool True on success, false on failure.
     */
    public function generateDailyStats(string $date): bool
    {
        try {
            // This is a complex operation that would typically involve multiple queries
            // For simplicity, we'll use a placeholder implementation
            
            // In a real implementation, you would:
            // 1. Count orders for the day
            // 2. Sum revenue for the day
            // 3. Count new vs returning customers
            // 4. Count products sold
            // 5. Calculate average order value
            
            $data = [
                'date' => $date,
                'orders_count' => 0, // Placeholder
                'revenue' => 0, // Placeholder
                'new_customers' => 0, // Placeholder
                'returning_customers' => 0, // Placeholder
                'products_sold' => 0, // Placeholder
                'avg_order_value' => 0 // Placeholder
            ];
            
            return $this->save($data);
        } catch (Exception $e) {
            error_log("Error generating daily stats: " . $e->getMessage());
            return false;
        }
    }
}