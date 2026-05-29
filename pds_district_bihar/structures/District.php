<?php

class District{
  public $Id;
  public $Name;

    /**
     * Get the value of Id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->Id;
    }

    /**
     * Set the value of Id
     *
     * @param mixed $Id
     *
     * @return self
     */
    public function setId($Id)
    {
        $this->Id = $Id;

        return $this;
    }

    /**
     * Get the value of name
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->Name;
    }

    /**
     * Set the value of name
     *
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->Name = $name;

        return $this;
    }


    function insert(District $District){

        return "INSERT INTO Districts(id,name) VALUES ('".$District->getId()."','".$District->getName()."')";

    }

    function delete(District $District){

        return "DELETE FROM Districts WHERE id='".$District->getId()."'";

    }

    function update(District $District){

      return  "UPDATE Districts SET name='".$District->getName()."' WHERE id = '".$District->getId()."'";

    }

}

 ?>
