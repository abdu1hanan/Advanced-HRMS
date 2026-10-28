<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalarySlipDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_title',
        'account_no',
        'iban',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
