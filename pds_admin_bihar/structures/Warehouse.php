<?php

class Warehouse {
    public $district;
    public $name;
    public $id;
    public $warehousetype;
    public $type;
    public $latitude;
    public $longitude;
    public $storage;
    public $uniqueid;
    public $active;
    public $actual_storage;
    public $factorial;

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

    public function getWarehousetype() {
        return $this->warehousetype;
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

    public function getStorage() {
        return $this->storage;
    }
	
	public function getUniqueid() {
        return $this->uniqueid;
    }
	
	public function getActive() {
        return $this->active;
    }
	
	public function getActual_storage() {
        return $this->actual_storage;
    }
	
	public function getFactorial() {
        return $this->factorial;
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

    public function setWarehousetype($warehousetype) {
        $this->warehousetype = $warehousetype;
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

    public function setStorage($storage) {
        $this->storage = $storage;
    }
	
	public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }
	
	public function setActive($active) {
        $this->active = $active;
    }
	
	public function setActual_storage($actual_storage) {
        $this->actual_storage = $actual_storage;
    }
	
	public function setFactorial($factorial) {
        $this->factorial = $factorial;
    }
	
	function insert(Warehouse $warehouse){
        return "INSERT INTO warehouse (district, name, id, warehousetype, type, latitude, longitude, storage, uniqueid, active, actual_storage, factorial) VALUES ('".$warehouse->getDistrict()."','".$warehouse->getName()."','".$warehouse->getId()."','".$warehouse->getWarehousetype()."','".$warehouse->getType()."','".$warehouse->getLatitude()."','".$warehouse->getLongitude()."','".$warehouse->getStorage()."','".$warehouse->getUniqueid()."','".$warehouse->getActive()."','".$warehouse->getActual_storage()."','".$warehouse->getFactorial()."')";
    }

    function delete(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }
	
	function deleteall(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE 1";
    }
	
	function logname(Warehouse $warehouse){

        return "SELECT name FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";

    }
	
	
	function check(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }
	
	function checkInsert(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE LOWER(id)=LOWER('".$warehouse->getId()."')";
    }
	
	function checkEdit(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE LOWER(id)=LOWER('".$warehouse->getId()."')";
    }

    function update(Warehouse $warehouse){
      return  "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',id = '".$warehouse->getId()."',warehousetype = '".$warehouse->getWarehousetype()."',type = '".$warehouse->getType()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',storage = '".$warehouse->getStorage()."',active = '".$warehouse->getActive()."',actual_storage = '".$warehouse->getActual_storage()."',factorial = '".$warehouse->getFactorial()."' WHERE uniqueid = '".$warehouse->getUniqueid()."'";
    }
	
	function updateEdit(Warehouse $warehouse){
      return  "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',warehousetype = '".$warehouse->getWarehousetype()."',type = '".$warehouse->getType()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',storage = '".$warehouse->getStorage()."',active = '".$warehouse->getActive()."',actual_storage = '".$warehouse->getActual_storage()."',factorial = '".$warehouse->getFactorial()."' WHERE id = '".$warehouse->getId()."'";
    }
}

?>