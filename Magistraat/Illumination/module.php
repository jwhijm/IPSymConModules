<?php
class Illumination extends IPSModule
{

    // IPS_Create($id)
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString("Devices", "");
        $this->RegisterPropertyInteger("RemoteControl", 0);

        $this->RegisterPropertyInteger("RCUpdateInterval", 60);
        $this->RegisterTimer("UpdateRC", 0, 'IL_RemoteControl(' . $this->InstanceID . ');');

        $this->RegisterPropertyInteger("DLUpdateInterval", 60);
        $this->RegisterTimer("UpdateDL", 0, 'IL_Daylight(' . $this->InstanceID . ');');

        $this->RegisterVariableBoolean("AllSwitch", "Verlichting", "~Switch");
        $this->EnableAction("AllSwitch");
    }

    // IPS_ApplyChanges($id) 
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval("UpdateRC", $this->ReadPropertyInteger("RCUpdateInterval") * 1000);
        $this->SetTimerInterval("UpdateDL", $this->ReadPropertyInteger("DLUpdateInterval") * 1000);
    }

    public function RequestAction($ident, $value)
    {
        //TODO Set State correct if Switch by other function.
        $this->SetValue($ident, $value);
        if ($value) {
            $this->SwitchDevices(true);
        } else {
            $this->SwitchDevices(false);
        }
    }
    /**
     * This Function are providing the Action functions
     *
     * IL_SwitchDevices($id);
     *
     */
    public function SwitchDevices(bool $action)
    {
        $arrString = $this->ReadPropertyString("Devices");
        $json = json_decode($arrString);

        //echo $json[0]->InstanceID;
        foreach ($json as $device) {
            $devicevar = $device->InstanceID;
            $devicetype = strval(IPS_GetInstance($device->InstanceID)["ModuleInfo"]["ModuleName"]);

            if (substr($devicetype, 0, 3) === "Z2D") {
                Z2D_SwitchMode($devicevar, $action);
                IPS_LogMessage("Illumination", "Switch ZigBee Device(group) On : $devicevar");
            } else if (substr($devicetype, 0, 6) === "Z-Wave") {
                ZW_SwitchMode($devicevar, $action);
                IPS_LogMessage("Illumination", "Switch Z-Wave Device(group) On : $devicevar");
            } else {
                IPS_LogMessage("Illumination", "Unsupported Device Id : $devicevar");
            }
        }
    }

    /**
     * This Function are providing the Action functions
     *
     * IL_RemoteControl($id);
     *
     */
    public function RemoteControl()
    {
        $lastchanged = IPS_GetVariable($this->ReadPropertyInteger("RemoteControl"))["VariableChanged"];
        $timedif = time() - $lastchanged;

        if ($timedif > ($this->ReadPropertyInteger("RCUpdateInterval") + 1)) {
            return;
        }

        echo GetValue($this->ReadPropertyInteger("RemoteControl"));
        switch (GetValue($this->ReadPropertyInteger("RemoteControl"))) {
            case 1000:
                $this->SwitchDevices(true);
                IPS_LogMessage("Illumination", "Switch Devices On by remote");
                break;
            case 1002:
                //NOT IMPLEMENTED
                break;
            case 1003:
                //NOT IMPLEMENTED
                break;
            case 2000:
                //NOT IMPLEMENTED
                break;
            case 2002:
                //NOT IMPLEMENTED
                break;
            case 2003:
                //NOT IMPLEMENTED
                break;
            case 3000:
                //NOT IMPLEMENTED
                break;
            case 3002:
                //NOT IMPLEMENTED
                break;
            case 3003:
                //NOT IMPLEMENTED
                break;
            case 4000:
                $this->SwitchDevices(false);
                IPS_LogMessage("Illumination", "Switch Devices Off by remote");
                break;
            case 4002:
                //NOT IMPLEMENTED
                break;
            case 4003:
                //NOT IMPLEMENTED
                break;
        }
    }
    /**
     * This Function are providing the Action functions
     *
     * IL_Daylight($id);
     *
     */
    public function Daylight()
    {
    }
}
