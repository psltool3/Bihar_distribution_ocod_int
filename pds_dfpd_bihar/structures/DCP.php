<?php

class DCP {
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
	
	function insert(DCP $dcp){
        return "INSERT INTO dcp (district, name, id, type, latitude, longitude, demand,demand_rice, uniqueid, active) VALUES ('".$dcp->getDistrict()."','".$dcp->getName()."','".$dcp->getId()."','".$dcp->getType()."','".$dcp->getLatitude()."','".$dcp->getLongitude()."','".$dcp->getDemand()."','".$dcp->getDemandrice()."','".$dcp->getUniqueid()."','".$dcp->getActive()."')";
    }

    function delete(DCP $dcp){
        return "DELETE FROM dcp WHERE uniqueid='".$dcp->getUniqueid()."'";
    }
	
	function deleteall(DCP $dcp){
        return "DELETE FROM dcp WHERE 1";
    }
	
	function logname(DCP $dcp){

        return "SELECT name FROM dcp WHERE uniqueid='".$dcp->getUniqueid()."'";

    }
	
	function check(DCP $dcp){
        return "SELECT * FROM dcp WHERE uniqueid='".$dcp->getUniqueid()."'";
    }
	
	function checkEdit(DCP $dcp){
        return "SELECT * FROM dcp WHERE LOWER(id)=LOWER('".$dcp->getId()."')";
    }
	
	function checkInsert(DCP $dcp){
        return "SELECT * FROM dcp WHERE LOWER(id)=LOWER('".$dcp->getId()."')";
    }

    function update(DCP $dcp){
     return  "UPDATE dcp SET district = '".$dcp->getDistrict()."',name = '".$dcp->getName()."',id = '".$dcp->getId()."',type = '".$dcp->getType()."',latitude = '".$dcp->getLatitude()."',longitude = '".$dcp->getLongitude()."',demand = '".$dcp->getDemand()."',demand_rice = '".$dcp->getDemandrice()."' WHERE uniqueid = '".$dcp->getUniqueid()."'";
    }
	
	function updateEdit(DCP $dcp){
      return  "UPDATE dcp SET district = '".$dcp->getDistrict()."',name = '".$dcp->getName()."',id = '".$dcp->getId()."',type = '".$dcp->getType()."',latitude = '".$dcp->getLatitude()."',longitude = '".$dcp->getLongitude()."',demand = '".$dcp->getDemand()."',demand_rice = '".$dcp->getDemandrice()."' WHERE id = '".$dcp->getId()."'";
    }
}  

?>