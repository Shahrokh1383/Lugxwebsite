<?php
namespace App\Models;

use App\Core\Model;

class UserSession extends Model 
{
    protected string $table = 'user_sessions';
    protected string $primaryKey  = 'id';
}
