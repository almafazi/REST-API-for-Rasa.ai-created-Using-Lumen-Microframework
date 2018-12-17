<?php

namespace App\Http\Controllers;

use App\User;
use App\Message;
use App\Quota;
use App\Furlough;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChatController extends Controller
{
    private 
        $response, 
        $status = 200, 
        $client, 
        $rasa_url,
        $rasa_ip;

    public function __construct()
    {
        session_start();
        $this->client = new \GuzzleHttp\Client();
        $this->rasa_url = 'http://localhost:5005/webhooks/rest/webhook';
        $this->rasa_ip = 'localhost';
    }
    
    public function index()
    {
       // $_SESSION['boy'] = 'boy';
        echo $_SESSION['boy'];
    
        //app('session')->put('tes', 'Welcome to Lumen REST API Framework For Rasa.AI Backend!');
        //echo app('session')->get('tes');
    }
    
    public function chat(Request $request) {
        
        if(empty($_SESSION['sender'])) {
            
            $_SESSION['sender'] = 0;
            
            $sender = 0;
            
        } else {
            
            $sender = $_SESSION['sender'];
            
        }
        
        if($request->input('query') == 'clear') {
            
            session_destroy();
            
            $sender = $_SESSION['sender'] = 0;
            
            $chats = $this->sendQuery('hi', $sender);
            
       } else {
            
            $chats = $this->sendQuery($request->input('query'), $sender);
        
        }
        
        $processedEntity = $this->processEntity($chats['intent'], $chats['entities'], $chats['response'], $request->input('query'));

        if($processedEntity['error'] == 1) {
        
            $errorMsg = $processedEntity['response'];
            
            $chats = $this->sendQuery('hi', $sender);
            
            $processedEntity = $this->processEntity($chats['intent'], $chats['entities'], $chats['response'], $request->input('query'));
            
            $processedEntity['response'] = $errorMsg.', '.$processedEntity['response'];
            
        };
        
        $resp = json_encode(['response' => $processedEntity['response'], 'session' => $_SESSION['sender'], 'intent' => $processedEntity['intent'], 'entity' => $processedEntity['entity']]);
        
        echo $resp;
        
    }
    
    public function processEntity($intent, $entities, $bot_answer, $text) {
        switch ($intent) {
            case 'nama':
                
                if(User::whereUsername($entities)->exists()) {
                    
                    $user = User::whereUsername($entities)->first();
                    
                    $_SESSION['sender'] = $user->id;
                
                    $user_id = $_SESSION['sender'];
                    
                    Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));
                    
                    return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                } else {
                    return array(
                        'success'  => false,
                        'response' => 'Sorry, nama anda tidak terdaftar di sistem kami, silahkan coba lagi :)',
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 1
                    );
                }
                
                break;
            case 'cuti_tipe': 
            
                $user_id = $_SESSION['sender'];
            
                $kuota = User::whereId($user_id)->whereType($entities)->pluck('type');
                
                return array(
                        'success'  => true,
                        'response' => $kuota,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                
                break;
            case 'tanggal_mulai':
                
                $_SESSION['tanggal_mulai'] = $entities;
                
                $user_id = $_SESSION['sender'];
                
                if($user_id != 0) Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));

                return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                
                break;
            case 'tanggal_selesai':
                
                $_SESSION['tanggal_selesai'] = $entities;
                
                $user_id = $_SESSION['sender'];
                
                if( $user_id != 0 ) Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));
                
                return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                
                break;
            case 'tipe_cuti':
                
                $_SESSION['tipe_cuti'] = $entities;
                
                $user_id = $_SESSION['sender'];
                
                if( $user_id != 0 ) Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));
                
                return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                
                break;
            case 'konfirmasi':
                
                if( isset($_SESSION['tanggal_mulai'])   &&
                    isset($_SESSION['tanggal_selesai']) &&
                    isset($_SESSION['tipe_cuti']) ) {                    
                    
                    $user_id         = $_SESSION['sender'];
                    $start_date      = date('Y-m-d', strtotime($this->tanggalIndo($_SESSION['tanggal_mulai'])));
                    $finish_date     = date('Y-m-d', strtotime($this->tanggalIndo($_SESSION['tanggal_selesai'])));
                    $total_days      = $this->calculateDays($start_date, $finish_date);
                    $additional_info = $tipe_cuti = $_SESSION['tipe_cuti'];
                    
                    Furlough::create(compact('user_id', 'start_date', 'finish_date', 'total_days', 'additional_info'));
                    
                    $quota = Quota::where('user_id', $user_id)->first();
                    
                    if( $tipe_cuti == 'tahunan' ) {
                        
                        if( $total_days < $quota->tahunan ) {
                            
                            $tahunan = $quota->tahunan - $total_days;
                            
                            Quota::update(compact('tahunan'));
                        
                        }
                        
                    }
                }
                
                if($user_id != 0) Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));
                
                return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
                
                break;
            default:
                if($_SESSION['sender']) {
                    $user_id = $_SESSION['sender']; 
                    Message::create(compact('user_id', 'bot_answer', 'text', 'intent', 'entities'));
                };
                
                return array(
                        'success'  => true,
                        'response' => $bot_answer,
                        'intent'   => $intent,
                        'entity'   => $entities,
                        'error'    => 0
                    );
        }
    }
    
    public function sendQuery($msg, $sender) {
        
        $data = '{"sender":"'.$sender.'","message":"'.$msg.'"}';
        
        $response = $this->client->request('POST', $this->rasa_url, [
            'body' => $data,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
        
        $result = json_decode($response->getBody()->getContents());
        
        $track = json_decode(file_get_contents('http://'.$this->rasa_ip.':5005/conversations/'.$_SESSION['sender'].'/tracker'));
        
        $intent = $track->latest_message->intent->name;
        $entities = (empty($track->latest_message->entities[0]->value)) ? "0" : $track->latest_message->entities[0]->value;
        $response = $result[0]->text;
        
        return compact('intent', 'entities', 'response');
    }
    
    
    
    /* ADDITIONAL FUNCTION */
    public function tanggalIndo($tanggal) {
        
        $tgl = array (
            'januari'   => 'january',
            'februari'  => 'february',
            'maret'     => 'march',
            'april'     => 'april',
            'mei'       => 'may',
            'juni'      => 'june',
            'juli'      => 'july',
            'agustus'   => 'august',
            'september' => 'september',
            'oktober'   => 'october',
            'november'  => 'november',
            'desember'  => 'december'
        );

        return strtr(strtolower($tanggal), $tgl);
    }
    
    public function calculateDays($start_Date, $finish_Date) {
        $startTimeStamp = strtotime($start_date);
        $endTimeStamp = strtotime($finish_date);

        $timeDiff = abs($endTimeStamp - $startTimeStamp);
        $numberDays = $timeDiff/86400;
        return intval($numberDays);
    }

}
