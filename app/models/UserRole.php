<?php
namespace App\Models;

use App\Core\Model;

class UserRole extends Model
{
    protected string $table = 'user_roles';
    
    /**
     * Find a user role by its name.
     *
     * @param string $name The name of the role (e.g., 'admin', 'customer').
     * @return array|false Returns role data as an associative array, or false if not found.
     */
    public function findByName(string $name): array|false
    {
        return $this->first(['name' => $name]);
    }
}
