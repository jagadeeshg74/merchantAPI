<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Customer;


class MerchantPoyalty extends Model
{
    //

	protected $table ='pty_cust_poyalty_card_merchant_hdr';

	protected $primaryKey = 'mp_id';
	 public  $timestamps =false;

    protected $fillable = [
		'mp_id'  ,
		'mp_cust_id' ,  
		'mp_merchant_id' , 
		'mp_card_id' ,
		'mp_poyals_accrued' ,  
		'mp_poyals_redeemed'  ,
		'mp_poyals_expired',
		'mp_poyals_balance',
		'mp_record_status',
		'mp_create_date',
        
    ];

 

 public function customer()
    {
        return $this->hasOne('App\Models\Customer','cm_cust_id');
    }


}
