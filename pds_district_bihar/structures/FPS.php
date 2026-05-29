<?php

class FPS {
    public $district;
    public $name;
    public $id;
    public $type;
    public $latitude;
    public $longitude;
    public $demand;
    public $demandrice;
    public $uniqueid;
    public $active;

    // Getter methods
    public function getDistrict() {
        return $this->district;
    }

    public function getName() {
        return $this->name;
    }

    public function getId() {
        return $this->id;
    }

    public function getType() {
        return $this->type;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getDemand() {
        return $this->demand;
    }
	
	public function getDemandrice() {
        return $this->demandrice;
    }
	
	public function getUniqueid() {
        return $this->uniqueid;
    }
	
	public function getActive() {
        return $this->active;
    }


    // Setter methods

    public function setDistrict($district) {
        $this->district = $district;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setDemand($demand) {
        $this->demand = $demand;
    }
	
	public function setDemandrice($demandrice) {
        $this->demandrice = $demandrice;
    }
	
	public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }
	
	public function setActive($active) {
        $this->active = $active;
    }
	
	function insert(FPS $fps){
        return "INSERT INTO fps (district, name, id, type, latitude, longitude, demand,demand_rice, uniqueid, active) VALUES ('".$fps->getDistrict()."','".$fps->getName()."','".$fps->getId()."','".$fps->getType()."','".$fps->getLatitude()."','".$fps->getLongitude()."','".$fps->getDemand()."','".$fps->getDemandrice()."','".$fps->getUniqueid()."','".$fps->getActive()."')";
    }

    function delete(FPS $fps){
        return "DELETE FROM fps WHERE uniqueid='".$fps->getUniqueid()."'";
    }
	
	function deleteall(FPS $fps){
        return "DELETE FROM fps WHERE 1";
    }
	
	function deletealldistrict(FPS $fps, $district){
        return "DELETE FROM fps WHERE LOWER(district)=Lower('".$district."')";
    }
	
	function logname(FPS $fps){

        return "SELECT name FROM fps WHERE uniqueid='".$fps->getUniqueid()."'";

    }
	
	function check(FPS $fps){
        return "SELECT * FROM fps WHERE uniqueid='".$fps->getUniqueid()."'";
    }
	
	function checkEdit(FPS $fps){
        return "SELECT * FROM fps WHERE LOWER(id)=LOWER('".$fps->getId()."')";
    }
	
	function checkInsert(FPS $fps){
        return "SELECT * FROM fps WHERE LOWER(id)=LOWER('".$fps->getId()."')";
    }

    function update(FPS $fps){
     return  "UPDATE fps SET district = '".$fps->getDistrict()."',name = '".$fps->getName()."',id = '".$fps->getId()."',type = '".$fps->getType()."',latitude = '".$fps->getLatitude()."',longitude = '".$fps->getLongitude()."',demand = '".$fps->getDemand()."',demand_rice = '".$fps->getDemandrice()."' ,active = '".$fps->getActive()."'WHERE uniqueid = '".$fps->getUniqueid()."'";
    }
	
	function updateEdit(FPS $fps){
      return  "UPDATE fps SET district = '".$fps->getDistrict()."',name = '".$fps->getName()."',id = '".$fps->getId()."',type = '".$fps->getType()."',latitude = '".$fps->getLatitude()."',longitude = '".$fps->getLongitude()."',demand = '".$fps->getDemand()."',demand_rice = '".$fps->getDemandrice()."',active = '".$fps->getActive()."' WHERE id = '".$fps->getId()."'";
    }
}  

?>