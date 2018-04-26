<?php
  $panel = new Pactl();
  if(isset($_REQUEST['id'])){
	  if(isset($_REQUEST['volume']))
	  {
		$panel->setVolume($_REQUEST['id'],$_REQUEST['volume']);
	  }
	  if(isset($_REQUEST['mute'])){
		  $panel->setMute($_REQUEST['id'],$_REQUEST['mute']);
	  }
	  if(isset($_REQUEST['sink'])){
		  $panel->move($_REQUEST['id'],$_REQUEST['sink']);
	  }
	  unset($panel);
	  $panel = new Pactl();
	}
	echo json_encode(objectToArray($panel));



  class Pactl {
	const CMD = "LANG=C pactl";
	public $sinks = array();
	public $inputs = array();
	
	public function __construct(){
		$this->update();
	}
	
	private function clear() {
		foreach($this->sinks as $key => &$value){
			unset($this->sinks[$key]);
		}
		unset($value);
		foreach($this->inputs as $key => &$value){
			unset($this->inputs[$key]);
		}
		unset($value);
	}
	
	private function update() {
		$this->clear();
				//Create output sinks
		exec(implode(" ", [Pactl::CMD, "list", Sink::CMD]), $output);
		$SinksOutputs = Sink::Split_Filter($output);
		foreach($SinksOutputs as $SinksOutput){
			$SinksOutputCleaned = Sink::Clean_Filter($SinksOutput);
			array_walk($SinksOutputCleaned, create_function('&$val', '$val = ltrim($val);')); 
			// Sink number = array key
			$id = substr($SinksOutputCleaned[0], strpos($SinksOutputCleaned[0], "#")+1);
			$this->sinks[$id] = new Sink($SinksOutputCleaned);
		}
	}
	
	public function setVolume($id,$volume) {
		if(substr($id,0,1)==="s"){
			$this->sinks[substr($id,1)]->setVolume($volume);
		}else{
			$this->inputs[substr($id,1)]->setVolume($volume);
		}
		$this->update();
	}
	
	public function setMute($id,$mute) {
		if(substr($id,0,1)==="s"){
			$this->sinks[substr($id,1)]->setMute($mute);
		}else{
			$this->inputs[substr($id,1)]->setMute($mute);
		}
		$this->update();
	}
	
	public function move($id,$sink) {
		$this->inputs[substr($id,1)]->move(substr($sink,1));
		$this->update();
	}
  }
	
	class Sink {
		const CMD = "sinks";
		public $id;
		public $name;
		public $mute;
		public $volume;
		
		public function __construct($data){
   		    // Sink input number
			$this->id = substr($data[0], strpos($data[0], "#")+1);
			// Sink description
			$this->name = substr($data[1], strpos($data[1], ":")+2);
			// Mute
			$this->mute = substr($data[2], strpos($data[2], ":")+2);
			// Volume
			preg_match_all('/([\d]+%)/', $data[3], $volumes);
			array_walk($volumes[0], create_function('&$val', '$val = rtrim($val,"%");'));
			$this->volume = array_sum($volumes[0]) / count($volumes[0]);
		}
		public function setVolume($value) {
			exec(implode(" ", [Pactl::CMD, "set-sink-volume", $this->id, $value . "%"]));
		}
		public function setMute($value) {
			exec(implode(" ", [Pactl::CMD, "set-sink-mute", $this->id, $value]));
		}
		
		static public function Clean_Filter($src_array){
			$res = array();
			$tags = array(
					"Sink",
					"Name",
					"Mute",
					"Volume"
				);
			$tag_id = 0;
			foreach($src_array as &$element)
			{
				if(strpos(ltrim($element),$tags[$tag_id]) === 0){
					$res[] = $element;
					$tag_id += 1;
				}
			}
			return $res;
		}
		public function Split_Filter($src_array) {
			$res = array();
			$sink = array();
			$record_on = 0;

			foreach($src_array as &$line)
			{
				if($this->Sink_Stop_Func($line)){
					$record_on = 0;
					$res[] = $sink;
				}
				if($record_on){
					$sink[] = $line;
				}
				if($this->Sink_Start_Func($line)){
					$record_on = 1;
					$sink = array();
					$sink[] = $line;
				}
			}
			return $res
		}

		public function Sink_Start_Func($data) {
			if(strpos($data,"Sink #") === 0){
				return true;
			}
			return false;

		}
		public function Sink_Stop_Func($data) {
			if(strpos($data,"Sink #") === 0){
				return true;
			}
			return false;

		}
	  }
	
	
	class SinkInput {
		const CMD = "sink-inputs";
		public $id;
		public $sink;
		public $mute;
		public $volume;
		public $name;

		public function __construct($data){
		   // Sink input number
			$this->id = substr($data[0], strpos($data[0], "#")+1);
			// Sink number
			$this->sink = substr($data[1], strpos($data[1], ":")+2);
			// Mute
			$this->mute = substr($data[2], strpos($data[2], ":")+2);
			// Volume
			//$this->volume = substr($data[3], strpos($data[3], ":")+2);
			preg_match_all('/([\d]+%)/', $data[3], $volumes);
			array_walk($volumes[0], create_function('&$val', '$val = rtrim($val,"%");'));
			$this->volume = array_sum($volumes[0]) / count($volumes[0]);
			// Name
			$this->name = trim(substr($data[4], strpos($data[4], "=")+3), '"');
		}
		public function setVolume($value) {
			exec(implode(" ", [Pactl::CMD, "set-sink-input-volume", $this->id, $value . "%"]));
		}
		public function setMute($value) {
			exec(implode(" ", [Pactl::CMD, "set-sink-input-mute", $this->id, $value]));
		}
		
		public function move($value) {
			exec(implode(" ", [Pactl::CMD, "move-sink-input", $this->id, $value]));
		}
		
		
		static public function sink_inputs_filter($data){
			$elements = array(
					"Sink Input",
					"Sink:",
					"Mute",
					"Volume:",
					"application.name"
				);
			$contained = false;
			foreach($elements as &$element)
			{
				if(strpos(ltrim($data),$element) === 0){
					$contained = true;
				}
			}
			return $contained;
		}
	}

function objectToArray($d) {
	if (is_object($d)) {
		// Gets the properties of the object
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		return array_map(__FUNCTION__, $d);
	} else {
		// Return array
		return $d;
	}
}

?>
