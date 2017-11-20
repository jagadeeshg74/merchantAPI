<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PoyaltyCard;

class Merchant extends Model
{
    //

	protected $table ='pty_merchant_master';

	protected $primaryKey = 'mm_merchant_id';

    protected $fillable = [
    
           
    ];

    
     public function user()
    {
        return $this->hasMany('App\Models\User','ur_merchant_id', 'mm_merchant_id');
    }
  



}
