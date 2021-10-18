<?php
class Illumination extends IPSModule
{

    // IPS_Create($id)
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString("Devices", "");
        $this->RegisterPropertyString("LxSensors", "");
        $this->RegisterPropertyInteger("RemoteControl", 0);

        $this->RegisterPropertyInteger("RCUpdateInterval", 60);
        $this->RegisterTimer("UpdateRC", 0, 'IL_RemoteControl(' . $this->InstanceID . ');');

        $this->RegisterPropertyInteger("DLUpdateInterval", 60);
        $this->RegisterTimer("UpdateDL", 0, 'IL_GetAvgIllumination(' . $this->InstanceID . ');');

        $this->RegisterVariableBoolean("AllSwitch", "Verlichting", "~Switch");
        $this->EnableAction("AllSwitch");

        $this->RegisterVariableFloat("Totallx", "Illumination", "~Illumination.F", 2);
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
        $this->SetValue($ident, $value);
        if ($value) {
            $this->SwitchDevices(true);
        } else {
            $this->SwitchDevices(false);
        }
    }
    /**
     * This Function are providing the Actions To Switch the devices On or Off
     * Depanding on the Given $action.
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

        $this->SetValue("AllSwitch", $action);
    }

    /**
     * This Function are providing the Action From the Remotecontrol
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
     * This Function is Calculation the avg Illumination functions
     *
     * IL_Daylight($id);
     *
     */
    public function GetAvgIllumination()
    {
        $arrString = $this->ReadPropertyString("LxSensors");
        $json = json_decode($arrString);
        $totallx = 0.0;

        //echo $json[0]->InstanceID;
        foreach ($json as $device) {
            $value = GetValueFloat($device->VarID);
            $weight = $device->Weight;
            $totallx += $value *  $weight;
        }

        $totallx = $totallx / count($json);
        $this->SetValue("Totallx", $totallx);
    }
}
