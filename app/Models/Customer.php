<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PoyaltyCard;

class Customer extends Model
{
    //

	protected $table ='pty_cust_master';

	protected $primaryKey = 'cm_cust_id';

    protected $fillable = [
    
           
    ];

    // each Customer HAS one Card 
    public function poyaltyCard() {
        return $this->hasOne('App\Models\PoyaltyCard','cp_cust_id'); // this matches the Eloquent model
    }

     public function merchantPoyalty() {
        return $this->hasOne('App\Models\MerchantPoyalty','mp_cust_id'); // this matches the Eloquent model
    }

 /* public function merchantPoyalty() {
        return $this->hasMany('App\Models\MerchantPoyalty','mp_cust_id'); // this matches the Eloquent model
    }
*/
  
}
