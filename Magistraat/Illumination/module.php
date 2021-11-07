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
        $this->RegisterTimer("AutomationTimer", 0, 'IL_Automation(' . $this->InstanceID . ');');

        $this->RegisterVariableBoolean("AllSwitch", "Verlichting", "~Switch");
        $this->EnableAction("AllSwitch");

        $this->RegisterPropertyFloat("DarkLuxValue", 20);
        $this->RegisterPropertyString("MorningOnTime", json_encode(array("hour" => 6, "minute" => 0, "second" => 0), true));
        $this->RegisterPropertyString("MorningMaxOffTime", json_encode(array("hour" => 8, "minute" => 30, "second" => 0), true));
        $this->RegisterPropertyString("AfternoonSwitchTime", json_encode(array("hour" => 16, "minute" => 0, "second" => 0), true));
        $this->RegisterPropertyString("AfternoonMaxOnTime", json_encode(array("hour" => 1, "minute" => 30, "second" => 0), true));

        $this->RegisterVariableFloat("Totallx", "Illumination", "~Illumination.F", 2);

        $this->RegisterAttributeBoolean("ToDark", false);
        $this->RegisterAttributeBoolean("Present", false);

        //$this->EnableAction(11581);

    }

    // IPS_ApplyChanges($id) 
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval("UpdateRC", $this->ReadPropertyInteger("RCUpdateInterval") * 1000);
        $this->SetTimerInterval("UpdateDL", $this->ReadPropertyInteger("DLUpdateInterval") * 1000);
        $this->SetTimerInterval("AutomationTimer", $this->ReadPropertyInteger("DLUpdateInterval") * 1000);
        //echo json_decode($this->ReadPropertyString("AfternoonMaxOnTime"));
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
     * This Function are providing the Automation To Switch the devices On or Off
     * Depanding on the Given $action.
     *
     * IL_Automation($id);
     *
     */
    public function Automation()
    {
        //return;
        $currentlux = $this->GetValue("Totallx");
        $dark = $this->ReadAttributeBoolean("ToDark");
        $present = $this->ReadAttributeBoolean("Present");

        if (($currentlux <= $this->ReadPropertyFloat("DarkLuxValue")) && (!$dark)) {
            $this->WriteAttributeBoolean("ToDark", true);
            IPS_LogMessage("Illumination", "Set ToDark Attrubute to True. Current lux : $currentlux ");
        } else if ($dark && $currentlux >= ($this->ReadPropertyFloat("DarkLuxValue"))) {
            $this->WriteAttributeBoolean("ToDark", false);
            IPS_LogMessage("Illumination", "Set ToDark Attrubute to False. Current lux : $currentlux");
        }

        $morningontime = json_decode($this->ReadPropertyString("MorningOnTime"), true);
        $morningofftime = json_decode($this->ReadPropertyString("MorningMaxOffTime"), true);
        $afternoonontime = json_decode($this->ReadPropertyString("AfternoonSwitchTime"), true);
        $eveningofftime = json_decode($this->ReadPropertyString("AfternoonMaxOnTime"), true);
        $currenthour = date("H");

        //Morning
        if ($currenthour >= 4 && $currenthour <= 12) {
            if (
                $this->InTimeSlot($morningontime, $morningofftime) &&
                ($this->GetValue("AllSwitch") == false)
            ) {
                IPS_LogMessage("Illumination", "Turn Illumination ON Morning");
                $this->SwitchDevices(true);
                return;
            }

            if (!$dark && $currentlux <= ($this->ReadPropertyFloat("DarkLuxValue") * 2.1)) {
                $dark = true;
            }

            if (
                (!$dark &&
                    (($this->GetValue("AllSwitch") == true))) ||
                ($this->InTimeSlot($morningontime, $morningofftime) == false &&
                    ($this->GetValue("AllSwitch") == true))
            ) {
                IPS_LogMessage("Illumination", "Turn Illumination OFF Morning");
                $this->SwitchDevices(false);
                return;
            }
        }

        //Evening
        if ($currenthour >= 12 && $currenthour <= 23) {
            $maxevening = array("hour" => "23", "minute" => "59", "second" => "59");
            if (
                $this->InTimeSlot($afternoonontime, $maxevening) &&
                ($this->GetValue("AllSwitch") == false) &&
                $dark
            ) {
                IPS_LogMessage("Illumination", "Turn Illumination ON Evening");
                $this->SwitchDevices(true);
                return;
            }

            if ($eveningofftime["hour"] <= 3) {
                return;
            } else {
                if (
                    !$this->InTimeSlot($afternoonontime, $eveningofftime) &&
                    ($this->GetValue("AllSwitch") == true) &&
                    !$present
                ) {
                    IPS_LogMessage("Illumination", "Turn Illumination OFF Evening");
                    $this->SwitchDevices(false);
                    return;
                }
            }
        }

        //Night
        if ($currenthour >= 00 && $currenthour <= 3) {
            $minnight = array("hour" => "0", "minute" => "0", "second" => "1");
            if (
                !$this->InTimeSlot($minnight, $eveningofftime) &&
                ($this->GetValue("AllSwitch") == true) &&
                !$present
            ) {
                IPS_LogMessage("Illumination", "Turn Illumination OFF Evening(Night)");
                $this->SwitchDevices(false);
                return;
            }
        }
    }


    function InTimeSlot(array $starttime, array $endtime)
    {
        $currenttime = array("hour" => date("H"), "minute" => date("i"), "second" => date("s"));
        //$currenttime = array("hour" => 0, "minute" => date("i"), "second" => date("s"));


        if ((($currenttime["hour"] == $starttime["hour"] && $currenttime["minute"] >= $starttime["minute"]) ||
                ($currenttime["hour"] == $endtime["hour"] && $currenttime["minute"] <= $endtime["minute"])) ||
            ($currenttime["hour"] >= $starttime["hour"] && $currenttime["hour"] <= $endtime["hour"])
        ) {
            return true;
        } else {
            return false;
        };
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
            case 1001:
                $this->SwitchDevices(true);
                IPS_LogMessage("Illumination", "Switch Devices On by remote");
                break;
            case 1002:
                $this->SwitchDevices(true);
                IPS_LogMessage("Illumination", "Switch Devices On by remote");
                break;
            case 1003:
                $this->SwitchDevices(true);
                IPS_LogMessage("Illumination", "Switch Devices On by remote");
                break;
            case 2000:
                //NOT IMPLEMENTED
                break;
            case 3000:
                //NOT IMPLEMENTED
                break;
            case 4000:
                $this->SwitchDevices(false);
                IPS_LogMessage("Illumination", "Switch Devices Off by remote");
                break;
            case 4001:
                $this->SwitchDevices(false);
                IPS_LogMessage("Illumination", "Switch Devices Off by remote");
                break;
            case 4002:
                $this->SwitchDevices(false);
                IPS_LogMessage("Illumination", "Switch Devices Off by remote");
                break;
            case 4003:
                $this->SwitchDevices(false);
                IPS_LogMessage("Illumination", "Switch Devices Off by remote");
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
