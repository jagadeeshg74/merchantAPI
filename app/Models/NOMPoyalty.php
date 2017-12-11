<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;

use App\Models\Customer;
use Log; 


class NOMPoyalty extends Model  {

	

    //pty_cust_poyalty_card_nom_hdr

	protected $table ='pty_cust_poyalty_card_nom_hdr';
	

	//protected $primaryKey = array('nr_cust_id','nr_nom_id','nr_card_id');
	 


	 protected $primaryKey = ['nr_cust_id', 'nr_nom_id','nr_card_id'];
     public $incrementing = false;
     public  $timestamps =false;

    protected $fillable = [
		// 'nr_id'  ,
		'nr_cust_id' ,  
		'nr_nom_id' , 
		'nr_card_id' ,
		'nr_poyals_accrued' ,  
		'nr_poyals_redeemed'  ,
		'nr_poyals_expired',
		'nr_poyals_balance',
		'nr_record_status',
		'nr_create_date',
		'nr_create_time',
		'nr_update_date',
		'nr_update_time'
        
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
