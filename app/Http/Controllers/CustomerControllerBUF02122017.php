<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers;

use App\Models\Redeem ;
use App\Models\RedeemDetail ;
use App\Models\Customer ;
use App\Models\PoyaltyCard;
use App\Models\NOMPoyalty ;

use Response;
use Log;  

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class CustomerController extends Controller
{
    //

    private $nom_id;
    private $cust_id   ;
    private $card_id =1;
    private $merchant_id  ;


		public function __Construct(){

			
		}


    public function index(Request $request){

      return Response::json(array('success' => true));
    }

		 public function store(Request $request)
	 {

	 	


    }
        /***
            find customers based on mobile nos


        ****/


     public function show($mobile_no ,$merchant_id)
    {

      DB::enableQueryLog();
    
      Log::info('Input merchant_id' . $merchant_id);

        $customer=  Customer::with('PoyaltyCard')
                    ->where('cm_mobile_no',$mobile_no)
                    ->with(['MerchantPoyalty' => function ($query) use ($merchant_id){
                      $query->where('mp_merchant_id',$merchant_id); 

            }])
                    ->get()->first();

          Log::info(DB::getQueryLog());             

         if (is_null($customer) )
         {
            return Response::json({$customer})  ;

         }
         
           // Get NOM details for merchant 

         $NOMDetail  =  DB::table('pty_NOM_dtl')
                        ->where( 'mo_merchant_id', '=', $merchant_id)
                        ->get()->first();

        if (is_null($NOMDetail)){

              Log::info('Merchant does not valid NOM details.');
              throw new Exception( 'Merchant does not have valid NOM agreement set-up');

        }else {

                // Log::info('Merchant NOM details.'.var_dump($NOMDetail));
                $WorkingArray = json_decode(json_encode($NOMDetail),true);
                $this->nom_id =  $WorkingArray["mo_nom_id"] ;
                Log::info('Merchant NOM ID .'. $this->nom_id);


                // NOM header details
                $NOMHdr =  DB::table('pty_NOM_hdr')
                      ->where( 'no_nom_id' , '=',$this->nom_id )
                      ->get()->first();

               // $WorkingNOMHdr = json_decode(json_encode($NOMHdr),true);
              //  $this->accruel_mul  = $this -> getAcruelRatio($WorkingNOMHdr["no_accrual_ratio"]);

                        
         }

           //    $customer =Response::json( $customer);    
             //  $data = $customer->getData();

            //   $decodedValue=json_encode($data);
               $WorkingArray = json_decode(json_encode($customer),true);

               Log::info('Customer ----------' .$customer);

               // Log::info('WorkingArray ----------' .$WorkingArray);



               $this -> cust_id = $WorkingArray['cm_cust_id'];


               $cust_nom_hdr= NOMPoyalty::where(
                            [
                              ['nr_cust_id',$this->cust_id],
                              ['nr_nom_id',$this->nom_id],
                              ['nr_card_id',$this->card_id]

                             ])->get()->first();


                $WorkingCustNOM = json_decode(json_encode($cust_nom_hdr),true);

                $customer['nom_poyalty'] = $WorkingCustNOM['nr_poyals_balance'];


         Log::info('Visits ----------' . $WorkingArray['merchant_poyalty']['mp_merchant_id']);
     //  Log::info('Visits ----------'. $data['merchant_poyalty']['mp_merchant_id']); 

         $enddate = new \DateTime('now');


        $time = new \DateTime('now');
        $newtime = $time->modify('-1 year')->format('Y-m-d');

         $visits_year = DB::table('pty_cust_merchant_bill')
                   ->select(DB::raw('count(*) as recent'))
                   ->where([
                        ['mb_cust_id', '=', $WorkingArray['cm_cust_id']],
                        ['mb_merchant_id' ,'=',$WorkingArray['merchant_poyalty']['mp_merchant_id']],
                        ['mb_merchant_bill_date','<=',$enddate],  
                        ['mb_merchant_bill_date','>=',$newtime]
                      ])                     
                   ->get();

          $recent_visits =(array)$visits_year[0];
          Log::info('counter id' . var_export($recent_visits, true) );
          Log::info ('recent visits : '.$recent_visits['recent']);    


          $visits_total = DB::table('pty_cust_merchant_bill')
                   ->select(DB::raw('count(*) as total'))
                   ->where([
                        ['mb_cust_id', '=', $WorkingArray['cm_cust_id']],
                        ['mb_merchant_id' ,'=',$WorkingArray['merchant_poyalty']['mp_merchant_id']]
                      ])                     
                   ->get();

          $total_visits =(array)$visits_total[0];
          Log::info('counter id' . var_export($total_visits, true) );
          Log::info ('recent visits : '.$total_visits['total']);    


        //  Log::info('Visits' . var_export($visits, true) );
          $customer['visits'] = array('recent' => $recent_visits['recent'] ,'total' => $total_visits['total']);

          // reviews ......

          $reviews =[];

      $reviews_total_collection = DB::table('pty_merchant_reviews')
                   ->select(DB::raw('count(*) as reviews_total'))
                   ->where([
                        ['mw_cust_id', '=', $WorkingArray['cm_cust_id']]
                      ])                     
                   ->get();

        
         if (isset($reviews_total_collection[0])){  
          Log:: info ('inside reviews_total_collection');

          $reviews_total = (array)$reviews_total_collection[0];
          Log::info ('reviews_total : '.$reviews_total['reviews_total']);  
          $reviews += array ('total' => $reviews_total['reviews_total'] );

      }

        $rating_five_collection = DB::table('pty_merchant_reviews')
                   ->select(DB::raw('count(*) as rating_five'))
                   ->where([
                        ['mw_cust_id', '=', $WorkingArray['cm_cust_id']],
                        ['mw_star_rating','=',5 ]
                      ])                     
                   ->get();

         if (isset($rating_five_collection[0])){  

        $rating_five = (array)$rating_five_collection[0];
        Log::info ('rating_five : '.$rating_five['rating_five']); 
        $reviews += array ('five_ratings' => $rating_five['rating_five'] );

      }

         $rating_collection = DB::table('pty_merchant_reviews')
                   ->select(DB::raw('mw_star_rating as rating'))
                   ->where([
                        ['mw_cust_id', '=', $WorkingArray['cm_cust_id']],
                        ['mw_merchant_id' ,'=',$WorkingArray['merchant_poyalty']['mp_merchant_id']]
                      ])                     
                   ->get();
        Log::info('rating_collection' . var_export($rating_collection, true) );
                   
       if (isset($rating_collection[0]) ){
        Log:: info ('inside rating_collection');
         $rating = (array)$rating_collection[0];
        Log::info ('rating : '.$rating['rating']); 
        $reviews += array ('rating' => $rating['rating'] );
      }else 
      {$reviews += array ('rating' => 0 ); }


        // Favorite Cusines
        // DB collec
        $cusine_collection = DB::table('pty_cust_merchant_bill')
                   
                ->join('pty_merchant_type_xref','pty_cust_merchant_bill.mb_merchant_id', '=', 'pty_merchant_type_xref.mf_merchant_id')
                ->join('pty_merchant_type_master', 'pty_merchant_type_xref.mf_merchant_sub_type', '=', 'pty_merchant_type_master.me_merchant_type_id')
                ->where([
                        ['mb_cust_id', '=', $WorkingArray['cm_cust_id']],
                        ['mb_merchant_id' ,'=',$WorkingArray['merchant_poyalty']['mp_merchant_id']]
                      ])
                ->distinct()         
                ->select('pty_merchant_type_xref.mf_merchant_sub_type','pty_cust_merchant_bill.mb_cust_id' , 'pty_merchant_type_master.me_merchant_sub_type')       
                ->get();


                //db collection array
                $cusine_array =$cusine_collection->toArray() ;

                $cusines = array();

                    if (isset($cusine_array[0])){
                       //records collection 

                       $cusines_collec = $cusine_array[0];


                       Log::info('cusines_collec  '.var_export($cusines_collec, true) );

                       $cusine  = (array)$cusine_array;

                         Log::info('cusine_array  '.var_export($cusine_array, true) );

                        // for each record 
                       foreach( $cusine_array as $cusine){

                      ;

                        $cusine_i  = (array)$cusine;

                        Log::info('cusine_i  '.var_export($cusine_i, true) );

                      //  Log::info('cusine  '.var_export($cusine_array, true) );
                        Log::info ('cusine_i : '.$cusine_i['mb_cust_id'] .'-----'.$cusine_i['mf_merchant_sub_type'].'----'.$cusine_i['me_merchant_sub_type']); 

                         $cusines = array_merge ($cusines,array ($cusine_i['me_merchant_sub_type']) );

                        Log::info('cusines  '.var_export($cusines, true) );

                        }

                       
                         
                        $customer ['cusines'] = $cusines ;

                    }
                   else {
                      $customer ['cusines'] = [] ;
                   } 

           //   Log::info('cusine_collection' . var_export($cusine_collection, true) );      
          //    Log::info(DB::getQueryLog());

          $customer['reviews'] = $reviews;  

       return Response::json( $customer);
                             
    }


    public function get_view($id)
{
   if (is_numeric($id))
   {
       $authorModel = Authors::find($id);
   }
   else
   {
       $column = 'name'; // This is the name of the column you wish to search

       $authorModel = Authors::where($column , '=', $id)->first();
   }

   return View::make('authors.view')
                ->with('author', $authorModel)
                ->with('title', $authorModel->name);

}
 


}