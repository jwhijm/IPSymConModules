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
        $this->RegisterAttributeInteger("Darklastupdate", time());

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
        $currenthour = date("H");

        $present = $this->ReadAttributeBoolean("Present");

        switch ($currenthour) {
            case 6:
            case 7:
            case 8:
            case 9:
            case 10:
            case 11:
                $this->Morning();
                break;
            case 12:
            case 13:
            case 14:
            case 15:
            case 16:
            case 17:
            case 18:
            case 19:
            case 20:
            case 21:
                $this->evening();
                break;
            case 22:
            case 23:
            case 0:
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
                $this->Night();
                break;
        }
    }

    function Morning()
    {
        $this->SwitchOnLux();

        $morningontime = json_decode($this->ReadPropertyString("MorningOnTime"), true);
        $morningofftime = json_decode($this->ReadPropertyString("MorningMaxOffTime"), true);
        $dark = $this->ReadAttributeBoolean("ToDark");

        if (
            $this->InTimeSlot($morningontime, $morningofftime) &&
            $this->GetValue("AllSwitch") == false &&
            $dark
        ) {
            IPS_LogMessage("Illumination", "Turn Illumination ON Morning");
            $this->SwitchDevices(true);
            return;
        }

        if (
            $this->InTimeSlot($morningontime, $morningofftime) == false &&
            $this->GetValue("AllSwitch") == true
        ) {
            IPS_LogMessage("Illumination", "Turn Illumination OFF Morning Outside TimeSlot");
            $this->SwitchDevices(false);
            return;
        }

        if (!$dark &&  $this->GetValue("AllSwitch") == true) {
            IPS_LogMessage("Illumination", "Turn Illumination OFF Morning Lux To High");
            $this->SwitchDevices(false);
            return;
        }
    }


    function Evening()
    {
        $this->SwitchOnLux();

        $afternoonontime = json_decode($this->ReadPropertyString("AfternoonSwitchTime"), true);
        $eveningofftime = json_decode($this->ReadPropertyString("AfternoonMaxOnTime"), true);
        $dark = $this->ReadAttributeBoolean("ToDark");

        if (
            $this->InTimeSlot($afternoonontime, $eveningofftime) &&
            ($this->GetValue("AllSwitch") == false) &&
            $dark
        ) {
            IPS_LogMessage("Illumination", "Turn Illumination ON Evening");
            $this->SwitchDevices(true);
            return;
        }
    }

    function Night()
    {
        $afternoonontime = json_decode($this->ReadPropertyString("AfternoonSwitchTime"), true);
        $eveningofftime = json_decode($this->ReadPropertyString("AfternoonMaxOnTime"), true);

        if (
            !$this->InTimeSlot($afternoonontime, $eveningofftime) &&
            ($this->GetValue("AllSwitch") == true)
        ) {
            IPS_LogMessage("Illumination", "Turn Illumination OFF Evening(Night)");
            $this->SwitchDevices(false);
            return;
        }
    }

    function SwitchOnLux()
    {
        $currentlux = $this->GetValue("Totallx");

        $darkthreshold = $this->ReadPropertyFloat("DarkLuxValue");
        $dark = $this->ReadAttributeBoolean("ToDark");

        $darkSwitchThreshold = time() - $this->ReadAttributeInteger("Darklastupdate");

        if ($darkSwitchThreshold <= 120) {
            return;
        }

        if (($currentlux <= $darkthreshold) && (!$dark)) {
            $this->WriteAttributeBoolean("ToDark", true);

            $this->WriteAttributeInteger("Darklastupdate", time());

            IPS_LogMessage("Illumination", "Set ToDark Attrubute to True. Current lux : $currentlux ");
        } else if ($dark && $currentlux >= $darkthreshold) {
            $this->WriteAttributeBoolean("ToDark", false);

            $this->WriteAttributeInteger("Darklastupdate", time());
            IPS_LogMessage("Illumination", "Set ToDark Attrubute to False. Current lux : $currentlux");
        }
    }

    function InTimeSlot(array $starttime, array $endtime)
    {
        $currenttime = array("hour" => date("H"), "minute" => date("i"), "second" => date("s"));

        //$currenttime = array("hour" => 23, "minute" => 26, "second" => 4);

        if ($starttime["hour"] - $endtime["hour"] > 0) {

            if ($currenttime["hour"] <= $endtime["hour"]) {
                if ($currenttime["minute"] > $endtime["minute"]) {

                    return false;
                }

                return true;
            }

            $endtime = array("hour" => "24", "minute" => "0", "second" => "0");
        }

        if ($currenttime["hour"] >= $starttime["hour"] && $currenttime["hour"] <= $endtime["hour"]) {

            if ($currenttime["hour"] == $starttime["hour"]) {
                if ($currenttime["minute"] < $starttime["minute"]) {
                    return false;
                }

                return true;
            }

            if ($currenttime["hour"] == $endtime["hour"]) {
                if ($currenttime["minute"] > $endtime["minute"]) {

                    return false;
                }

                return true;
            }

            return true;
        }

        return false;
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

        $sensorCount = count($json);

        foreach ($json as $device) {
            $value = GetValueFloat($device->VarID);
            $lastUpdate = time() - IPS_GetVariable($device->VarID)["VariableChanged"];

            if ($lastUpdate > 43200) {
                $sensorCount = $sensorCount - 1;
                IPS_LogMessage("Illumination", "Lux Sensor not updated for 12h " . $device->VarID . " Ignored");
                continue;
            }

            $weight = $device->Weight;
            $totallx += $value *  $weight;
        }

        $totallx = $totallx / $sensorCount;
        $this->SetValue("Totallx", $totallx);
    }
}
