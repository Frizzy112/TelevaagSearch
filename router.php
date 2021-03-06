<?php
session_start();
require_once "searchengine/Elasticsearch/Query.php";
require_once "searchengine/ResultList.php";
require_once "searchengine/Document.php";
require_once "searchengine/Author.php";

function render( $vars ){
   $templatename = func_get_arg(0);
   
   if( func_num_args() == 2 && is_array(func_get_arg(1)) ){
      $args = func_get_arg(1);
   }
   else{
      $args = func_get_args();
      array_shift($args);
   }
   
   foreach( $args as $key => $val ){
      $$key = $val;
   }
   
   include( $templatename );
}

if( !isset($_SERVER['PATH_INFO']) OR $_SERVER['PATH_INFO'] == NULL ){
   if( isset( $_GET['q'] ) && isset( $_GET['persons'] ) ){
      
      $query = new ElasticsearchQuery();
      $data = $query->persons( $_GET['q'], "kamervraag" );
      
      $personlist = array();
      
      foreach( $data->hits->hits as $hit ){
         foreach( $hit->fields->persons as $person ){
            if( $person == " () ()" )
               break;
            if( isset( $personlist[$person] ) ){
               $personlist[$person]++;
            }
            else{
               $personlist[$person] = 1;
            }
         }
      }
      
      arsort( $personlist );
      $partijen = array();
      
      foreach( $personlist as $person => $cnt ){
         $split1 = explode( "(", $person );
         $split2 = explode( ")", $split1[1] );
         $partij = $split2[0];
         
         if( in_array($partij, array("minister", "minister-president", "staatssecretaris") ) ){
            continue;
         }
         
         if( isset( $partijen[$partij] ) ){
            $partijen[$partij]++;
         }
         else{
            $partijen[$partij] = 1;
         }
      }
      render( "searchengine/site/persons.php", array( "personlist" => $personlist, "q" => $_GET['q'], "partijen" => $partijen ) );
  
      
   }
   else if( isset( $_GET['q'] ) && isset( $_GET['person'] ) ){
      $query = new ElasticsearchQuery();
      
      $source = "";
      if( isset($_GET['source']) && in_array($_GET['source'], array("telegraafarticle", "kamervraag")) ){
         $source = $_GET['source'];
      }
      
      $display['page_num'] = ((empty($_GET['page'])) ? 1 : $_GET['page']);
      
      $data  = $query->searchByPerson( $_GET['q'],$_GET['person'] , $source, $display['page_num'] );
      $display = array();
      $display['result_total_num'] = $data->hits->total;
      $display['processing_time'] = $data->tooktime;
      
      // Moving this to a seperate function would be better.. but for now i'm lazy
      
      $resultlist =  new ResultList();
      foreach( $data->hits->hits as $data ){
         $resultlist->add(
               new Document(
                  $data->_id, $data->_source->title, NULL, 
                 $data->_source->link, $data->highlight->text[0],
                 $data->_source->date, $data->_source->text
               )
            );
      }
      
      $display['resultset'] = $resultlist;
   
      render( "searchengine/site/results.php", $display );
   }
   else if( isset( $_GET['q'] ) ){
   
      $query = new ElasticsearchQuery();
      
      $source = "";
      if( isset($_GET['source']) && in_array($_GET['source'], array("telegraafarticle", "kamervraag", "kamervraag-bm25")) ){
         $source = $_GET['source'];
      }
      
      $display = array();
      $display['page_num'] = ((empty($_GET['page'])) ? 1 : $_GET['page']);
      $data  = $query->search( $_GET['q'], $source, $display['page_num'] );
      
      $display['result_total_num'] = $data->hits->total;
      $display['processing_time'] = $data->tooktime;
      
      // Moving this to a seperate function would be better.. but for now i'm lazy
      
      $resultlist =  new ResultList();
      foreach( $data->hits->hits as $data ){
         $resultlist->add(
               new Document(
                  $data->_id, $data->_source->title, NULL, 
                 $data->_source->link, $data->highlight->text[0],
                 $data->_source->date, $data->_source->text
               )
            );
      }
      
      if( isset( $_GET['gelukkig'] ) ){
         header( "Location: " . $resultlist->first()->getLink() );
         exit;
      }
      
      $display['resultset'] = $resultlist;
   
      render( "searchengine/site/results.php", $display );
   }
   else if( isset( $_GET['advanced'] ) ){
   	
   		$search_array = $_SESSION[$_GET['advanced']]['query'];

      $display = array();
      $display['page_num'] = ((empty($_GET['page'])) ? 1 : $_GET['page']);
   		$query = new ElasticsearchQuery();
   		$data = $query->advanced($search_array, $_SESSION[$_GET['advanced']]['source'], $display['page_num'] );
   		
	      $display['result_total_num'] = $data->hits->total;
	      $display['processing_time'] = $data->tooktime;
	      
	      
	      // Moving this to a seperate function would be better.. but for now i'm lazy
	      
	      $resultlist =  new ResultList();
	      foreach( $data->hits->hits as $data ){
	         $resultlist->add(
	               new Document(
	                  $data->_id, $data->_source->title, NULL, 
	                 $data->_source->link, $data->highlight->text[0],
	                 $data->_source->date, $data->_source->text
	               )
	            );
	      }
	      
	      $display['resultset'] = $resultlist;
	   
	      render( "searchengine/site/results.php", $display );
	      
   }
   else if( isset( $_GET['timeline'] ) || isset( $_GET['timeline_advanced'] ) ){
   
   	
      $query = new ElasticsearchQuery();
      
      if(isset($_GET['timeline'])) {
		  $data  = $query->search( $_GET['timeline'], "", 0, 40 );
      } else if(isset($_GET['timeline_advanced'])) {
		  $data = $query->advanced($search_array, $_SESSION[$_GET['timeline_advanced']]['source']);
      }
      
      $resultlist =  new ResultList();
      foreach( $data->hits->hits as $data ){
         $resultlist->add(
               new Document(
                  $data->_id, $data->_source->title, NULL, 
                 $data->_source->link, $data->highlight->text[0],
                 $data->_source->date, $data->_source->text
               )
            );
      }
   
      if(isset($_GET['timeline'])) {
		  render( "searchengine/site/timeline.php", array( "q" => $_GET['timeline'], "resultset" => $resultlist ));
      } else if(isset($_GET['timeline_advanced'])) {
		  render( "searchengine/site/timeline.php", array( "advanced" => $_GET['timeline_advanced'], "resultset" => $resultlist ));
      }
      
   }
   else if( isset( $_GET['cloud'] ) OR isset( $_GET['doccloud'] ) OR isset( $_GET['cloud_advanced'] ) ){
   
      function rmsymbols( $word ){
         foreach( array("(", ")", "'", ";", ".", ",", "\"", "\\", "\n", "\r") as $s ){
            $word = str_replace($s, "", $word);
         }
         return $word;
      }
   
      $query = new ElasticsearchQuery();
      
      if( isset( $_GET['cloud'] ) ){
         $q = $_GET['cloud'];
         $data  = $query->search( $_GET['cloud'], "", 0, 100 );
      }
      else if(isset( $_GET['cloud_advanced'] )) {
		  $data = $query->advanced($search_array, $_SESSION[$_GET['cloud_advanced']]['source']);
      }
      else{
         $q = $_GET['orc'];
         $data = $query->id( $_GET['doccloud'] );
      }
      
      $resultlist =  new ResultList();
      foreach( $data->hits->hits as $data ){
         $resultlist->add(
               new Document(
                  $data->_id, $data->_source->title, NULL, 
                 $data->_source->link, $data->highlight->text[0],
                 $data->_source->date, $data->_source->text
               )
            );
      }
      
      $wordset = array();
      
      foreach( $resultlist as $result ){
      
         // First do content stuff.
         $content = $result->getFullText();
         
         $splode = explode(" ", $content);
         foreach( $splode as $word ){
            
            $word = rmsymbols($word);
         
            if(!isset($wordset[$word]))
               $wordset[$word] = 1;
            else
               $wordset[$word]++;
         }
         
         // Title stuff.
         $content = $result->getTitle();
         $splode = explode(" ", $content);
         foreach( $splode as $word ){
         
            $word = rmsymbols($word);
         
            if(!isset($wordset[$word]))
               $wordset[$word] = 3;
            else
               $wordset[$word] = $wordset[$word]+3;
         }
         
      }
      
      foreach( array("aan", "de", "over", "het", "een", "en", "van", "met", "in", "of", "per", "voor", "is", "te", "dat", "op", "zijn", "niet", "die", "u", "door", "om", "ook", "er", "bij", "dit", "hij", "zij", "tot", "al", "ik", "heeft", "geen", "niet", "mijn", "De", "als", "hun", "hen", "Een", "Het", "naar", "ja", "nee", "was", "In", "in", "dan", "had", "uit", "gaan", "tegen", "zou", "daar", "Dat", "via", "zal", "hebben", "waarmee", "worden", "wordt", "deze", "nog", "onze", "ons", "Van", "nu", "Ingezonden", "Antwoorden", "Vragen", "lid", "der") as $stopwoord ){
         unset($wordset[$stopwoord]);
      }
      
      arsort( $wordset );
      $wordset = array_slice($wordset, 0, 50);
      
      // Normalize
      
      $minscore = end($wordset);
      $maxscore = reset($wordset);
      
      $newwordset = array();
      foreach( $wordset as $word => $score ){
         $newwordset[$word] = ( (($score-$minscore)/$maxscore)*10+1 );
      }
      if( isset( $_GET['cloud'] ) OR isset( $_GET['doccloud'] ) ) {
	      render( "searchengine/site/cloud.php", array( "q" => $q, "topwords" => $newwordset ));
      } else {
	      render( "searchengine/site/cloud.php", array( "advanched" => $_GET['cloud_advanced'], "topwords" => $newwordset ));
      }
      
   }
   else{
      render( "searchengine/site/index.php" );
   }
}
else{
   
}

?>