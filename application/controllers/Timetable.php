<?php
   
   require APPPATH . '/libraries/REST_Controller.php';
   // use Restserver\Libraries\REST_Controller;
     
class Timetable extends REST_Controller {
    
    /**
     * @return Response
    */
    public function __construct() {
       parent::__construct();
    }
       
    /**
     * @return Response
    */
	public function index_get(){
        # Initialised all the necessary variables
        $trainNo = $this->get('trainNo');
        $trainCode = 'Couldn\'t fetch';
        $trainName = 'Couldn\'t fetch';
        $runsOn = 'Couldn\'t fetch';
        $catering = 'Couldn\'t fetch';
        $coachPosition = [];
        $scheduleData = [];
        $res = null;
        # If train number is not specified in the url then return with an error
        if($trainNo==null){
            $data = array(
                'status' => FALSE,
                'message' => 'Please specify a train number'
            );
            $this->response($data,REST_Controller::HTTP_NOT_FOUND);
            return;
        }

        # MAKING THE FIRST API CALL WITH THE PROVIDED TRAIN NUMBER, THIS REQUEST WILL RETURN THE ACTUAL TRAIN CODE REQUIRED FOR DATA
        $dom = new DOMDocument();
        $initialData = file_get_contents('https://indiarailinfo.com/shtml/list.shtml?LappGetTrainList/'.$trainNo.'/0/0/0');
        $dom->loadHTML($initialData);
        $xpath = new DOMXPath($dom);
        // XPath queries along with the DOMXPath::query method can be used to return the list of elements that are searched for by the user.
        $row1 = $xpath->query('//table[@class="dropdowntable"]/tr[@class="rowM1"]/td');
        if(count($row1) !== 5){
            $data = array(
                'status' => FALSE,
                'message' => 'Invalid train number'
            );
            $this->response($data,REST_Controller::HTTP_NOT_FOUND);
            return;
        }
        # EXTRACTING THE TRAIN CODE, TRAIN NAME AND SCHEDULE
        $trainCode = $row1[0]->nodeValue;
        $trainName = $row1[2]->nodeValue;
        $row2 = $xpath->query('//table[@class="dropdowntable"]/tr[@class="rowm2"]/td');
        $runsOn = 'Runs on: '.$row2[1]->nodeValue;

        
        # GETTING TIMETABLE HERE
        $mainPageUrl = 'https://indiarailinfo.com/train/'.$trainCode.'?';
        $mainPagedom = new DOMDocument();
        $mainPage = file_get_contents($mainPageUrl);
        @$mainPagedom->loadHTML($mainPage);
        $xpath = new DOMXPath($mainPagedom);
        $catering = $xpath->query('//div[@class="topcapsule"]/table/tr/td[1]/div[2]/div/div[1]')[0]->nodeValue;
        $coachPositionList = $xpath->query('//div[@class="num"]');
        
        foreach ($coachPositionList as $tag) {
            array_push($coachPosition, $tag->nodeValue);
        }
        if(!empty($coachPosition)){
           $coachPosition = implode('←', $coachPosition);
        }

        $timetableList = $xpath->query('//div[@class="newschtable newbg inline"]/div[@class=""]');
        foreach ($timetableList as $value) {
            $arrivalTime = $value->getElementsByTagName('div')[6]->nodeValue;
            $departureTime = $value->getElementsByTagName('div')[8]->nodeValue;
            $stationName = $value->getElementsByTagName('div')[3]->nodeValue. '('.$value->getElementsByTagName('div')[2]->nodeValue.')';
            $avgDelay = $value->getElementsByTagName('div')[10]->nodeValue;

            # BUILDING THE OBJECT TO INSERT IT INTO THE MAIN ARRAY
            $currentObj = array(
                'stationName' => $stationName,
                'arrivalTime' => ($arrivalTime == '')?'Source':$arrivalTime,
                'departureTime' => ($departureTime == '')?'Dstn':$departureTime,
                'distance' => $value->getElementsByTagName('div')[13]->nodeValue.' KM',
                'day' => $value->getElementsByTagName('div')[12]->nodeValue,
                'platform' => $value->getElementsByTagName('div')[11]->nodeValue,
                'avgDelay' => ($avgDelay == '') ? '-' : $avgDelay
            );

            array_push($scheduleData, $currentObj);
        }

        # FINAL RESPONSE
        $res = array(
            "trainName"     =>  $trainName,
            "daysRunning"   =>  $runsOn,
            "scheduleData"  =>  $scheduleData, 
            "catering"      =>  $catering,
            "coachPosition" =>  $coachPosition
        );
        // print_r(var_dump(json_encode($scheduleData)));
        // die();
        
        $this->response($res, REST_Controller::HTTP_OK);
	}
    	
}