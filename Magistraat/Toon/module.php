<?php
class Toon extends IPSModule
{

    // IPS_Create($id)
    public function Create()
    {
        parent::Create();
    }

    // IPS_ApplyChanges($id) 
    public function ApplyChanges()
    {

        parent::ApplyChanges();
    }

    /**
     * This Function are providing the Action functions
     *
     * TO_RequestAction($id);
     *
     */
    public function RequestAction($ident, $value)
    {
    }

    /**
     * This Function are providing the Action functions
     *
     * TO_Module($id);
     *
     */
    public function Module()
    {
    }
}
