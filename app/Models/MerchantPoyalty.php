<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Customer;
use Log; 


class MerchantPoyalty extends Model
{
    //

	protected $table ='pty_cust_poyalty_card_merchant_hdr';
	

	protected $primaryKey = ['mp_merchant_id', 'mp_cust_id','mp_nom_id','mp_card_id' ];

	 public  $timestamps = false;
	 public  $incrementing = false;

    protected $fillable = [
		//'mp_id'  ,
		'mp_cust_id' ,  
		'mp_merchant_id' , 
		'mp_nom_id' ,
		'mp_card_id' ,
		'mp_poyals_accrued' ,  
		'mp_poyals_redeemed'  ,
		'mp_poyals_expired',
		'mp_poyals_balance',
		'mp_record_status',
		'mp_create_date',
		'mp_update_date',
		'mp_update_time'
        
    ];

 

 public function customer()
    {
        return $this->hasOne('App\Models\Customer','cm_cust_id');
    }


   /**
 * Set the keys for a save update query.
 *
 * @param  \Illuminate\Database\Eloquent\Builder  $query
 * @return \Illuminate\Database\Eloquent\Builder
 */
protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
{
    $keys = $this->getKeyName();
    if(!is_array($keys)){
        return parent::setKeysForSaveQuery($query);
    }

    foreach($keys as $keyName){
        $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
    }

    return $query;
}

/**
 * Get the primary key value for a save query.
 *
 * @param mixed $keyName
 * @return mixed
 */
protected function getKeyForSaveQuery($keyName = null)
{
    if(is_null($keyName)){
        $keyName = $this->getKeyName();
    }

    if (isset($this->original[$keyName])) {
        return $this->original[$keyName];
    }

    return $this->getAttribute($keyName);
}





}
