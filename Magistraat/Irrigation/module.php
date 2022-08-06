<?php
class Irrigation extends IPSModule
{

    // IPS_Create($id)
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger("RainMeter", 0);
        $this->RegisterPropertyInteger("RainMeterHour", 0);
        $this->RegisterPropertyInteger("RainThreshold", 0);
        $this->RegisterPropertyInteger("TodayMaxTemp", 0);
        $this->RegisterPropertyInteger("TempThreshold", 15);
        $this->RegisterPropertyBoolean("NightTime", false);
        $this->RegisterPropertyInteger("WaterCoolDown", 600);

        $this->RegisterPropertyInteger("WaterSwitch", 0);
        $this->RegisterPropertyInteger("MaxOntime", 6000);

        $this->RegisterPropertyInteger("UpdateInterval", 6000);


        $this->RegisterAttributeString("LastNotificationSend", "0");
        $this->RegisterPropertyInteger("TelegramVar", 0);

        $this->RegisterTimer("Update", 0, 'IR_MonitorRain(' . $this->InstanceID . ');');
    }

    // IPS_ApplyChanges($id) 
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval("Update", $this->ReadPropertyInteger("UpdateInterval") * 1000);
    }

    /**
     * This Function are providing the Action functions
     *
     * IR_RequestAction($id);
     *
     */
    public function RequestAction($ident, $value)
    {
    }

    public function MonitorRain()
    {
        IPS_LogMessage("Irrigation", "Evaluate Rain");

        $currentRainFall = GetValueFloat($this->ReadPropertyInteger("RainMeter"));
        //$currentRainFall = 4.0;
        $todayMaxTemp = GetValue($this->ReadPropertyInteger("TodayMaxTemp"));
        $waterNeeded = false;

        if (
            $todayMaxTemp > $this->ReadPropertyInteger("TempThreshold") &&
            $currentRainFall <  $this->ReadPropertyInteger("RainThreshold")
        ) {
            $waterNeeded = true;
        }

        $this->WaterControl($waterNeeded);
    }


    function WaterControl(bool $action)
    {
        $childs = IPS_GetChildrenIDs($this->ReadPropertyInteger("WaterSwitch"));
        $waterSwitch = 0;
        $waterBattery = 0;

        if (count($childs) == 2) {
            foreach ($childs as $value) {
                $varValue = GetValue($value);
                if (gettype($varValue) === "boolean") {
                    $waterSwitch = $value;
                } else {
                    $waterBattery = $value;
                }
            }
        }

        $waterSwitchStateTime = time() - IPS_GetVariable($waterSwitch)["VariableChanged"];
        $waterSwitchState = GetValueBoolean($waterSwitch);

        $raincurrenthour = (GetValue($this->ReadPropertyInteger("RainMeterHour")) == 0.0) ? false : true;

        if (GetValue($waterBattery) < 25) {
            if ($waterSwitchState == true) {
                Z2D_SwitchMode($this->ReadPropertyInteger("WaterSwitch"), false);
            }

            IPS_LogMessage("Irrigation", "Battery WaterSwitch to Low!");
            $this->SendTelegramNotification("Battery WaterSwitch To Low! Value: " . GetValue($waterBattery) . "%", 14400);
            return;
        }

        if ($waterSwitchStateTime >= ($this->ReadPropertyInteger("MaxOntime") + 600) && $waterSwitchState == true) {
            Z2D_SwitchMode($this->ReadPropertyInteger("WaterSwitch"), false);
            IPS_LogMessage("Irrigation", "WaterSwitch Is Still ON!!");
            $this->SendTelegramNotification("WaterSwitch Is Still ON!! Time last update: " . $waterSwitchStateTime, 600);
        }

        switch ($waterSwitchState) {
            case false:

                if ($this->ReadPropertyBoolean("NightTime") == True) {
                    $currenthour = date("H");
                    if ($currenthour < 1 || $currenthour > 6) {
                        IPS_LogMessage("Irrigation", "Not Night Time");
                        return;
                    }
                }

                if ($action == true && $waterSwitchStateTime < $this->ReadPropertyInteger("WaterCoolDown")) {
                    IPS_LogMessage("Irrigation", "Switch Cooldown not reached");
                    return;
                }

                if (GetValue($waterBattery) < 20) {
                    IPS_LogMessage("Irrigation", "Battery WaterSwitch to Low!");
                    return;
                }

                if ($action == true && $raincurrenthour == false) {
                    Z2D_SwitchMode($this->ReadPropertyInteger("WaterSwitch"), true);
                    IPS_LogMessage("Irrigation", "Switch ZigBee Device Water On");
                }

                break;

            default:
                if ($raincurrenthour == true) {
                    Z2D_SwitchMode($this->ReadPropertyInteger("WaterSwitch"), false);
                    IPS_LogMessage("Irrigation", "Switch ZigBee Device Water Off - Because of rain");
                }

                if ($waterSwitchStateTime >= $this->ReadPropertyInteger("MaxOntime")) {
                    Z2D_SwitchMode($this->ReadPropertyInteger("WaterSwitch"), false);
                    IPS_LogMessage("Irrigation", "Switch ZigBee Device Water Off - Maxtime Exceeded");
                }

                break;
        }
    }


    function SendTelegramNotification(string $message, string $sendinterval)
    {
        $notificatoinLastSend = $this->ReadAttributeString("LastNotificationSend");
        $currentDate = time();
        $timeLastSend = $currentDate - $notificatoinLastSend;

        $telegram = $this->ReadPropertyInteger("TelegramVar");
        if ($telegram ==  0) {
            IPS_LogMessage("Heating", "Telegram Bot Var not set, message not send");
            return;
        }

        if ($timeLastSend >= $sendinterval) {
            $this->WriteAttributeString("LastNotificationSend", $currentDate);
            TB_SendMessage($telegram, $message);
        }
    }
}
