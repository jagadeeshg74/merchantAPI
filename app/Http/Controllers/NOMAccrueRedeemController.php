<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers;

use App\Models\NOMPoyalty ;
use App\Models\MerchantPoyalty;
use App\Models\MerchantPoyaltyDtl ;
use App\Models\PoyaltyCard;

use Response;
use Log;  
use Validator;
use Exception;
use ValidationException;
use ValidatesRequests;
use Illuminate\Support\Facades\DB;


use Mail;

define("A",  20, true);
define("B",  40, true); 
define("C", 100, true);


class NOMAccrueRedeemController extends Controller
{
    //
    private $n_index =0;
    private $pty_NOM_hdr ;
    private $pty_merchant_hdr ;
    private $cust_id   ;
    private $merchant_id  ;
    private  $bill_amount  ;
    private  $redeem_amount ;
    private  $final_amount ;
    private  $bill_no   ;
    private  $bill_date   ;
    private  $current_balance = 0;
    private  $new_balance = 0;
    private $new_merchant_balance =0;
    private $new_nom_balance =0;
    private $accruel_mul;
    private $accrued_points =0;
    private $nom_id;
    private $card_id =1;
    private $poyaltycard;
    private $pty_nom_merchant_hdr;
    private $merchant_redeem_points =0 ;

	public function __Construct(){

		}
    
    public function index(Request $request){

        return Response::json(array('success' => true));
    }


	public function store(Request $request)
	 {

          // DB::enableQueryLog();

    	 	 // get the Request Object's value and store.....

          $this->cust_id       = $request->input('cust_id');
          $this->merchant_id   = $request->input('merchant_id');
          $this->bill_amount   = $request->input('bill_amount');   
          $this->bill_no       = $request->input('bill_no');
          $this->bill_date     = $request->input('bill_date');
          $this->current_balance = 0;
          $this->new_balance = 0;

          if ($request->input('redeem_amount')){
              $this->redeem_amount = $request->input('redeem_amount');
          }
          else 
              $this->redeem_amount = 0;


          $this->final_amount  = $this->bill_amount - $this->redeem_amount; 
           
          $accruel_mul = 1 ;

          Log::info('****************** NOM  Accrue Redeem Request  **********************');
          Log::info('Bill_amount , Redeem amount  Bill Date  Merchant :'.$this->bill_amount.'-------'. $this->redeem_amount.'----'. $this->bill_date .'----- ' .$this->merchant_id  ."----");
          Log::info('Merchant ID :'.$this->merchant_id);
      
        //  Validate the required fields 

        $validator = Validator::make($request->all(), [
                  'bill_date'       => 'required|date',
                  'bill_no'         => 'required',  
                  'bill_amount'     => 'required | numeric |min:1',
                  'redeem_amount'   => 'numeric |min:0',
                  'cust_id'         => 'required',
                  'merchant_id'     => 'required',
                  // Todo : add validation for redeem amount < bill_amount     
              ]);
        
       
try{      

   if ($validator->fails()) {
                    Log::info('Validation Errors occured '.$validator->messages());
                    throw new Exception( 'Validation Errors occured '.$validator->messages());         
                  // todo Throw validation Exception    
          }


        // DB::enableQueryLog();
       

          // Get NOM details for merchant 

         $NOMDetail  =  DB::table('pty_NOM_dtl')
                        ->where( 'mo_merchant_id', '=', $this->merchant_id)
                        ->get()->first();

        if (is_null($NOMDetail)){

              Log::info('Merchant does not valid NOM details.');
              throw new Exception( 'Merchant does not have valid NOM agreement set-up');

        }else {

                // Log::info('Merchant NOM details.'.var_dump($NOMDetail));
                $WorkingArray = json_decode(json_encode($NOMDetail),true);
                $this->nom_id =  $WorkingArray["mo_nom_id"] ;
                Log::info('Merchant NOM ID.'. $this->nom_id);


                // NOM header details
                $NOMHdr =  DB::table('pty_NOM_hdr')
                      ->where( 'no_nom_id', '=',$this->nom_id )
                      ->get()->first();

                $WorkingNOMHdr = json_decode(json_encode($NOMHdr),true);
                $this->accruel_mul  = $this -> getAcruelRatio($WorkingNOMHdr["no_accrual_ratio"]);

                if ($this->accruel_mul == 0 ){

                    Log::info('Invalid accruel ratio for Merchant.');
                    throw new Exception( 'Invalid accruel ratio for Merchant.');

                }           
         }

          DB::beginTransaction(); 

         
         $this -> createNOMHeader();
         $this -> createNOMMerchantDetails();
         $this -> createMerchantBill();
         $this -> updatePoyaltyCard();

         DB::commit();
         Log::info(' Accrue Redeem transaction successfully completed .'); 

         $this-> sendNotifications();

         return Response::json(array('success' => true ,'poyalty_card' => $this-> poyaltycard, 
          'nom_poyalty' => array('poyals_balance' => $this -> pty_NOM_hdr ->nr_poyals_balance , 'nom_accruel_factor' => $this->accruel_mul) ,
          'merchant_poyalty' => $this-> pty_merchant_hdr)); 



    }
    catch (Exception $excep)
    {
         DB::rollback();
         Log::debug('********  Exception occured in customer redeem  : '.$excep->getMessage().$excep->getTraceAsString().'****************');     
         return Response::json(array('success' => false ,'msg' => $excep->getMessage()));

    }
	
    }


    public function getTransCounter (){

    DB::update("UPDATE pty_system_settings
                       SET ss_settings_Val_1 = (@cur_value := ss_settings_Val_1) + 1
                       WHERE ss_settings_key ='TRANSCOUNT'" ); 

                     $counter_no = DB::select("SELECT @cur_value" );
                     $count =(array)$counter_no[0];
              //       Log::info('counter id' . var_export($counter_no, true) );
               //      Log::info ('count'.$count['@cur_value']);                       
                     $counter_no = DB::select("SELECT @cur_value" );
                     $count =(array)$counter_no[0];
            
                     return $count['@cur_value'];


    }


//  Send Notifications 

    public function sendMessage($data){

       // $msg = $data['TransType'] . " ".$data['Cashpoints'] ." on ".date("M d,Y")." at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of". $data['BalanceCashpoints'] .".";

        $msg = $data['CustomerName']." - " .$data['CustomerMobile'] . " Accrued with ".$this-> accrued_points . " cashpoints and redeemed with "   . $this-> redeem_amount ." cashpoints . Current Balance : ".$data['BalanceCashpoints'] ;

     
        $msg = str_replace(" ","%20",$msg);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://online.chennaisms.com/api/mt/SendSMS?user=ommanivannan&password=abcd123&senderid=poylty&channel=Trans&DCS=0&flashsms=0&number=".$data['CustomerMobile']."&text=".$msg."&route=28");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);

 
        }


      public function sendEmail($data){


         Log::info('Sending Email  .....'.json_encode($data));
         $data = json_decode(json_encode($data), True);
         if  (!isset($data['CustomerEmail']) || trim($data['CustomerEmail'])==='')
         {  
                 Log::info('Invalid Email id is  :--'.$data['CustomerEmail'].'----for customer :'. $data['CustomerName'] );

         }else {
                Log::info('Sending Email valid email id is  : '.$data['CustomerEmail']);

                   Mail::send('redeemnotify', $data ,function ($message) use ($data) {

                      $message->from('support@poyalty.com', 'poyalty');
                      $message->subject('Redeem/Accrue Notification - poyalty');
                      $message->replyTo("support@poyalty.com","poyalty");
                      $message->to( $data['CustomerEmail'] );

                  });

                if(count(Mail::failures()) > 0){
                    
                    Log::debug("There was one or more failures in sending email . They were: <br />");

                   foreach(Mail::failures() as $email_address) {
                       Log::debug( " Email failed for - $email_address- <br />");
                    }

                }else
                {
                //  Log::info('mail sent successfully !');
                  Log::info('Email Completed .');
                }
      }
  }


public function createNOMHeader(){


     // Check if record exits for Customer with NOM ID , else create one......

     // Get poyalty NOM  header  1. pty_cust_poyalty_card_nom_hdr 

     $this->pty_NOM_hdr = NOMPoyalty::where(
                            [
                              ['nr_cust_id',$this->cust_id],
                              ['nr_nom_id',$this->nom_id],
                              ['nr_card_id',$this->card_id]

                             ])->get()->first();
                            

      


        if (is_null($this-> pty_NOM_hdr))
        {     
         //  Log::info('new record creating : pty_NOM_hdr');
            // Create header Record .....
            $this-> pty_NOM_hdr = new NOMPoyalty(
                    [
                      'nr_cust_id' =>  $this-> cust_id ,
                      'nr_card_id' =>  $this-> card_id ,
                      'nr_nom_id'  => $this-> nom_id,
                      'mp_poyals_balance' =>  floor($this-> bill_amount/$this-> accruel_mul ) , 
                      'mp_poyals_accrued' =>  floor($this-> bill_amount/$this-> accruel_mul ) ,
                      'nr_create_date' => date("Y/m/d") ,
                      'nr_update_date' => date("Y/m/d") ,  
                    ]);
                    
         }


      //2. pty_cust_poyalty_card_merchant_hdr 
/*   $this->pty_merchant_hdr = DB::table('pty_cust_poyalty_card_merchant_hdr')
                              ->where([
                                ['mp_cust_id',$cust_id],
                                ['mp_nom_id',$nom_id],
                                ['mp_card_id',$card_id],
                                ['mp_merchant_id',$merchant_id]
                               ])->get()->first();     */

   $this->pty_merchant_hdr = MerchantPoyalty::where([
                                ['mp_cust_id', $this-> cust_id ],
                                ['mp_merchant_id',$this-> merchant_id],
                                ['mp_card_id',$this-> card_id],
                                ['mp_nom_id',$this-> nom_id],
                                ['mp_record_status','A'],
                             // ['mp_card_id',"=", $poyaltycard['cp_card_id']]  
                              ])->get()->first(); 

                              

    if (is_null($this->pty_merchant_hdr))
    {
        // Create header Record .....

      Log::info('new record creating : pty_NOM_hdr');

         $this->pty_merchant_hdr = new MerchantPoyalty(
                    [
                    'mp_cust_id'     =>   $this->cust_id ,
                    'mp_merchant_id' =>   $this->merchant_id ,
                    'mp_card_id'     =>   $this->card_id ,
                    'mp_nom_id'      =>   $this->nom_id ,
                    'mp_create_date' =>    date("Y/m/d") ,
                    'mp_update_date' =>    date("Y/m/d") ,  
                    'mp_record_status' => 'A'
                  ]
              );

    } 

    if (($this->pty_NOM_hdr )&&($this->pty_merchant_hdr))

    {
      $total = DB::table('pty_cust_poyalty_card_merchant_hdr')
                ->where([
                        ['mp_cust_id',$this-> cust_id],
                        ['mp_nom_id',$this-> nom_id]
                        ])
                ->sum('mp_poyals_balance');

        log::info ("Total Merchant Balance  :" . $total);  
        log::info ("NOM balance : " . $this->pty_NOM_hdr -> nr_poyals_balance);

        if ( ( $total >  $this->pty_NOM_hdr -> nr_poyals_balance) || ( $total <  $this->pty_NOM_hdr -> nr_poyals_balance) )
        {
         throw new Exception ('Incorrect Data ,please check NOM balance and nom merchant balance are  different. NOM balance :'.$this-> pty_NOM_hdr -> nr_poyals_balance ." --- Total Merchant Balance  :" . $total );
        }
    }

}


public function createNOMMerchantDetails(){

       if ($this->redeem_amount > 0 ) { //    create redeem  record
                if ($this->redeem_amount > $this-> pty_NOM_hdr -> nr_poyals_balance ){
                        throw new Exception ('Redeem amount ..'.$this->redeem_amount.'..cannot be greater than available cash points ..'.$this-> pty_NOM_hdr -> nr_poyals_balance);
                } else {
                        
      Log::info (" Accrued current NOM balance : ". $this-> pty_NOM_hdr -> nr_poyals_balance ."  Accrued : ". $this-> pty_NOM_hdr -> nr_poyals_accrued );       
                        $this->redeemNOMPartners();

                    }  // if final amount  ....
                  }   //  redeem amount 
                           
            //  create a accrued record                    
        if ( ( $this->final_amount > 0 ) && ( $this->final_amount > $this->accruel_mul) ) { 
       Log::info ("Accrued current NOM balance : " . $this-> pty_NOM_hdr -> nr_poyals_balance ."  Accrued : ". $this-> pty_NOM_hdr -> nr_poyals_accrued );
       
            $this->accrued_points = floor( $this-> final_amount/$this-> accruel_mul) ;

          Log::info (" Final Amount : ". $this->final_amount ." Accrued Points : " . $this->accrued_points );
          Log::info("Merchant balance Before : " . $this-> pty_merchant_hdr -> mp_poyals_accrued."----".$this-> pty_merchant_hdr -> mp_poyals_redeemed."----".$this-> pty_merchant_hdr -> mp_poyals_balance );
          
            $this-> pty_merchant_hdr -> mp_poyals_accrued +=  $this->accrued_points;
            $this-> pty_NOM_hdr -> nr_poyals_accrued +=  $this->accrued_points;

            $this-> pty_merchant_hdr -> mp_poyals_balance  = $this-> pty_merchant_hdr -> mp_poyals_balance+  $this->accrued_points - $this-> merchant_redeem_points; 


       // $this-> pty_merchant_hdr -> mp_poyals_balance  = $this-> pty_merchant_hdr -> mp_poyals_accrued - $this-> pty_merchant_hdr -> mp_poyals_redeemed ; 
          Log::info("Merchant balance After : " . $this-> pty_merchant_hdr -> mp_poyals_accrued."----".$this-> pty_merchant_hdr -> mp_poyals_redeemed."----".$this-> pty_merchant_hdr -> mp_poyals_balance );


            $this-> pty_NOM_hdr -> nr_poyals_balance  +=  $this->accrued_points ;

         //    $this-> pty_merchant_hdr -> mp_poyals_balance  -= $this->total_redeem_points;
           
            $this-> pty_merchant_hdr -> mp_update_date = date("Y/m/d");
            $this-> pty_NOM_hdr -> nr_update_date = date("Y/m/d") ;

            $this->addMerchantTransDetail();

          } 

      if (   ($this-> pty_merchant_hdr -> save()) &&   
              ($this-> pty_NOM_hdr -> save()))
                 {
                     // Log::info(DB::getQueryLog()); 
                      Log::info('new NOM Balance : '.$this-> pty_NOM_hdr -> nr_poyals_balance ." new NOM Accrued  :".$this-> pty_NOM_hdr -> nr_poyals_accrued );
               }
                 else {
                      Log::info('error in creating /updating NOM Merchant Poyalty Header !');
                    throw new Exception ('Error in creating /updating NOM Merchant Poyalty Header !'); 
            }      

}

public function createMerchantBill(){

          //  PTY_CUST_MERCHANT_BILL  
            DB::table('pty_cust_merchant_bill')->insert(
                [
                  'mb_cust_id' =>  $this-> cust_id ,
                  'mb_merchant_id' => $this-> merchant_id   ,
                  'mb_card_id'  =>  1,
                  'mb_transaction_id'        => $this-> merchant_id.date("ymd").$this->getTransCounter()  ,
                  'mb_merchant_bill_No'      =>  $this-> bill_no ,
                  'mb_merchant_bill_date'    =>  $this-> bill_date ,
                  'mb_bill_amount'           =>    $this-> bill_amount  ,
                  'mb_poyals_redeemed'       =>  is_null($this-> redeem_amount) ? 0: $this-> redeem_amount ,
                  'mb_amount-paid_by_poyals' =>  is_null($this-> redeem_amount) ? 0: $this-> redeem_amount*$this-> accruel_mul   ,
                  'mb_final_amount-paid' =>  $this-> final_amount  ,
                  'mb_record_status'     => 'A'  ,
                  'mb_create_date'       => DB::raw('CURDATE()')
                ]
            );
       
             Log::info(' PTY_CUST_MERCHANT_BILL  record created.'); 


}
private function addMerchantTransDetail()
{

      Log::info('Creating accrued record for final bill amount ____'.$this->final_amount );
                  //  add  details 

             
              $transactionid=$this->merchant_id.date("ymd").$this->getTransCounter();
                DB::table('pty_cust_poyalty_card_merchant_dtl')->insert(    
                //  $merch_dtl_accrued = new MerchantPoyaltyDtl (
                    [
                  'pd_cust_id'            => $this-> cust_id ,
                  'pd_merchant_id'        => $this-> merchant_id ,
                  'pd_nom_id'             => $this-> nom_id ,
                  'pd_card_id'            => $this-> card_id ,
                  'pd_transaction_id'     => $transactionid ,
                  'pd_transaction_type'   => 'A', //Accrued
                  'pd_poyals_added'       =>  $this->accrued_points ,
                  'pd_poyals_balance'     => $this-> pty_NOM_hdr -> nr_poyals_balance ,
                  'pd_merchant_bill_No'   => $this->bill_no ,
                  'pd_merchant_bill_date' => $this->bill_date ,
                  'pd_create_date'      => DB::raw('CURDATE()')
                  ]);   

          //  $merch_dtl_accrued -> save();

        Log::info('Created Merchant Poyalty Detail accrued record .');


}


public function updatePoyaltyCard()
{

   $this-> poyaltycard= PoyaltyCard::where('cp_cust_id',$this->cust_id)->get()->first();

      if (is_null($this-> poyaltycard)){
                //  Log::info('Poyalty Card Header does not exist for this customer .');
                  // throw new Exception ('Poyalty Card does not exist for given Customer :'.$cust_id) ;

                  //  PTY_CUST_POYALTY_CARD_HDR
                      DB::table('pty_cust_poyalty_card_hdr')->insert(
                      [
                          'cp_cust_id' =>  $this-> cust_id ,
                          'cp_card_id'  =>  1,
                          'cp_poyals_accrued'  =>   $this-> accrued_points ,
                          'cp_poyals_redeemed' =>   0 ,
                          'cp_poyals_expired'  =>  0  ,
                          'cp_poyals_balance'  => $this-> accrued_points ,
                          'cp_record_status'   => 'A'  ,
                          'cp_create_date'=> DB::raw('CURDATE()')
                      ]
                  );

              //    Log::info(' PTY_CUST_POYALTY_CARD_HDR  record created.');  
                  $poyaltycard= PoyaltyCard::where('cp_cust_id',$this -> cust_id)->get()->first();


                 }else {
                 
                 //     Log::debug('Poyalty Card  : '.json_encode($this-> poyaltycard));
                        // Update Poyalty Card **************** // *****************
                      $this-> poyaltycard->cp_poyals_accrued   += $this-> accrued_points;
                      $this-> poyaltycard->cp_poyals_redeemed  += $this-> redeem_amount;
                      $this-> poyaltycard->cp_poyals_balance    =  $this-> poyaltycard->cp_poyals_accrued - $this-> poyaltycard->cp_poyals_redeemed - $this-> poyaltycard->cp_poyals_expired;
                      $this-> poyaltycard->save();
                 //    Log::debug(' Poyalty Card Updated  _____cp_poyals_balance : '.$this-> poyaltycard['cp_poyals_balance']);
                 
                 }      




}

public function updatePoyaltyCardXRef()
{
         /*         

      // Poyalty Card XREF      
      $poyaltyCardXref = DB::table('pty_cust_poyalty_card_xref')
                        ->where('cl_cust_id',$cust_id)->get()->first();
      
        if (is_null($poyaltyCardXref)){
                Log::info('Poyalty Card does not exist for this customer .');
                 throw new Exception ('Poyalty Card does not exist for given Customer :'.$NotifiData ['CustomerName'].
                  'Customer Id '.$cust_id) ;
          }   

          */
}

private function getAcruelRatio($accruel_ratio){

        switch($accruel_ratio){

                case 'A' : return  A ;
                        break ; 
                case 'B' :   return B ;
                          break ;
                case 'C' : return C;
                      break ;
                case 'D' : return D;
                        break;  
                default :   return 0 ;
                                       
         }

}


private function redeemNOMPartners(){

            $merchant_redeem_points =0;

         $nom_merchants = DB::table('pty_NOM_dtl')
                              -> where('mo_nom_id',$this->nom_id)
                              -> select('mo_merchant_id')
                              -> get();

         $merchant_count =  count($nom_merchants);

         Log::info ('Merchant counts : '. $merchant_count); 
         
         /*
         if (($this->redeem_amount % $merchant_count ) == 0)
            {  

              Log::info (" Redeem Amount  : " . $this->redeem_amount );
        }else {

          $this->redeem_amount  -= ($this->redeem_amount % $merchant_count );
          Log::info ("New redeem amount : " . $this->redeem_amount );
        }     
       */

         $WorkingArrayNOMMerchants = json_decode( json_encode($nom_merchants) ,true);  

         $this-> current_nom_balance =  $this->pty_NOM_hdr->nr_poyals_balance ;

                foreach ( $WorkingArrayNOMMerchants as $merchant)
                {


              if ($merchant['mo_merchant_id'] == $this ->merchant_id )  {
                  Log::info ("Current Merchant "); 


              }
                   Log::info(' Merchant Id : '. $merchant['mo_merchant_id']);

                   $this-> pty_nom_merchant_hdr = MerchantPoyalty::where([

                                ['mp_cust_id',"=", $this-> cust_id ],
                                ['mp_merchant_id' ,"=",$merchant['mo_merchant_id']],
                                ['mp_card_id',$this-> card_id],
                                ['mp_nom_id',$this-> nom_id],
                                ['mp_record_status',"=",'A'],
                             // ['mp_card_id',"=", $poyaltycard['cp_card_id']]  
                    
                              ])->get()->first();


        if ($this-> pty_nom_merchant_hdr && ($this-> pty_nom_merchant_hdr -> mp_poyals_balance > 0)){


          Log::info("Poyal Balance ". $this-> pty_nom_merchant_hdr -> mp_poyals_balance ." / ".$this-> current_nom_balance  .' * '.$this-> redeem_amount);

          $this->redeem_points = floor(( $this-> pty_nom_merchant_hdr -> mp_poyals_balance / $this-> current_nom_balance  ) * $this-> redeem_amount) ;



          if ($merchant['mo_merchant_id'] == $this ->merchant_id )  {
                  Log::info ("Current Merchant ".$this ->merchant_id ); 
                  $this->merchant_redeem_points = $this->redeem_points;

              }

                 

                 Log::info("merchant redeem amount :" . $this->redeem_points ); 
                 $this->pty_nom_merchant_hdr -> mp_poyals_redeemed +=  $this->redeem_points;
                 $this->pty_nom_merchant_hdr -> mp_poyals_balance  -=  $this->redeem_points;

                 Log::info(" Redeeming points  : ".$this-> redeem_points ." for merchant : ". $merchant['mo_merchant_id'] ." mp_poyals_redeemed :".$this->pty_nom_merchant_hdr -> mp_poyals_redeemed . " mp_poyals_balance " .$this->pty_nom_merchant_hdr -> mp_poyals_balance );


                 Log::info ("NOM merchant Balance :".$merchant['mo_merchant_id']." ----  ". $this->pty_nom_merchant_hdr -> mp_poyals_balance  );

                    $this-> pty_nom_merchant_hdr-> save();

                     $this -> pty_NOM_hdr -> nr_poyals_redeemed += $this->redeem_points;  
                    $this -> pty_NOM_hdr -> nr_poyals_balance -= $this->redeem_points;

              if ( ($this-> pty_NOM_hdr -> nr_poyals_balance  < 0) || ($this-> pty_nom_merchant_hdr -> mp_poyals_balance < 0 )){
                        throw new Exception ('NOM / Merchant Points balance cannot be less than zero ,Unable to perform transaction ,Please contact Adminstrator !'  );
                }

               // generate transation id 
               $transactionid=$this->merchant_id.date("ymd").$this->getTransCounter(); 

               DB::table('pty_cust_poyalty_card_merchant_dtl')->insert(    
              // $merch_dtl_redeem = new MerchantPoyaltyDtl (
                [
                  'pd_cust_id'            =>  $this-> cust_id ,
                  'pd_merchant_id'        =>  $this-> merchant_id  ,
                  'pd_nom_id'             =>  $this-> nom_id,
                  'pd_card_id'            =>  $this-> card_id ,
                  'pd_transaction_id'     =>  $transactionid ,
                  'pd_poyals_redeemed'    =>  $this-> redeem_points,
                  'pd_transaction_type'   =>  'R' ,  
                  'pd_poyals_balance'     =>  $this -> pty_NOM_hdr -> nr_poyals_balance , 
                  'pd_merchant_bill_No'   =>  $this->bill_no ,
                  'pd_merchant_bill_date' =>  $this->bill_date ,
                  'pd_create_date'        =>  date("Y/m/d"),
                  'pd_merchant_id2'       =>  $merchant['mo_merchant_id']

                ]);   
               // $merch_dtl_redeem ->save();  

                Log::info('created Merchant Poyalty Detail Redeem record .');
              
            }
            }




}

private function sendNotifications(){

        $Customer = DB::table('pty_cust_master')
                           ->where( 'pty_cust_master.cm_cust_id', '=', $this-> cust_id)
                          ->select('pty_cust_master.cm_Name','pty_cust_master.cm_town','pty_cust_master.cm_address_1','pty_cust_master.cm_mobile_no','pty_cust_master.cm_email')
                           ->get()->first();
                           
        $WorkingArray = json_decode(json_encode($Customer),true);
                          
        Log::info('valid Customer : ' .json_encode($Customer));


        $data = array( 'CustomerId' =>$this->cust_id,
                             'MerchantId' => $this-> merchant_id,
                            'CustomerName' => $WorkingArray ['cm_Name'] ,
                            'CustomerMobile' => $WorkingArray ['cm_mobile_no'],
                            'CustomerEmail' =>  $WorkingArray ['cm_email']);

       $Merchant = DB::table('pty_merchant_master')
                   ->where( 'pty_merchant_master.mm_merchant_id', '=', $this->merchant_id )
                  ->select('pty_merchant_master.mm_Name','pty_merchant_master.mm_town','pty_merchant_master.mm_address_1')
                   ->get()->first();

         $WorkingArray = json_decode(json_encode($Merchant),true);
        Log::info('Valid Merchant ----------' .json_encode($Merchant));


        $data['MerchantName'] = $WorkingArray['mm_Name'] ;
        $data['MerchantTown'] = $WorkingArray['mm_town'] ;
        $data['MerchantAddress'] = $WorkingArray['mm_address_1'] ;
        $data['accrued_points'] = $this-> accrued_points ;
        $data['redeem_amount'] = $this-> redeem_amount ;
        $data['BalanceCashpoints']=$this-> pty_NOM_hdr -> nr_poyals_balance;


         DB::table('pty_system_notifications')->insert(

                  [
                    'sx_cust_id' =>  $data['CustomerId'] ,
                    'sx_notification_date' => date("Y/m/d") ,
                    'sx_create_date' => date("Y/m/d") ,
                    'sx_notification_time' =>date("H:i:sa"), 
                    'sx_notificatin_short_message' => "Accrued with" .$this-> accrued_points  ." cashpoints and Redeemed with ". $this->redeem_amount." cashpoints on ".date("M d,Y")." at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of ". $this -> pty_NOM_hdr -> pd_poyals_balance .".",
                    'sx_notificatin_Long_message'=>
                    "Accrued ".  $this-> accrued_points." on ". date("M d,Y")."  at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of ". $this -> pty_NOM_hdr -> pd_poyals_balance .". Pl. use poyalty cashpoints and save your money."
                  ]
              );

            try {
                  $this->sendMessage($data);
                  Log::info('SMS Completed .');
            }

            catch (Exception $excep){

               Log::debug('********  Exception occured in customer redeem SMS : '.$excep->getMessage().$excep->getTraceAsString().'****************');
            }    

         try{
              $this->sendEmail($data);
            Log::info('Email Completed .');
        }
          catch(Exception $excep){
             Log::debug('********  Exception occured in customer redeem email : '.$excep->getMessage().$excep->getTraceAsString().'****************');
          
        }


      }



public function checkDataConsistency(){






}



}