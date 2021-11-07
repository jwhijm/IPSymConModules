<?php
class Heating extends IPSModule
{

    // IPS_Create($id)
    public function Create()
    {
        parent::Create();

        $this->RegisterVariableFloat("HighTemp", "High Temp", "~Temperature", 1);
        $this->EnableAction("HighTemp");

        $this->RegisterVariableFloat("LowTemp", "Low Temp", "~Temperature", 2);
        $this->EnableAction("LowTemp");

        $this->RegisterVariableFloat("CurrentTemp", "Current Temp", "~Temperature", 3);

        $this->RegisterVariableBoolean("Heating", "Heating", "~Switch", 4);
        $this->EnableAction("Heating");

        $this->RegisterAttributeBoolean("Manual", false);
        $this->RegisterAttributeString("LastNotificationSend", "0");

        $this->RegisterPropertyInteger("BufferVar", 0);
        $this->RegisterPropertyInteger("TelegramVar", 0);

        $this->RegisterPropertyInteger("UpdateInterval", 6000);
        $this->RegisterPropertyInteger("MaxTemp", 71);
        $this->RegisterPropertyInteger("MinTemp", 20);

        $this->RegisterPropertyString("DomoticzIP", "0.0.0.0");
        $this->RegisterPropertyInteger("DomoticzPort", 8080);
        $this->RegisterPropertyInteger("Domoticzidx", 0);

        $this->RegisterTimer("Update", 0, 'HT_MonitorTemp(' . $this->InstanceID . ');');
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
     * HT_RequestAction($id);
     *
     */
    public function RequestAction($ident, $value)
    {

        if ($ident == "CurrentTemp") return;

        if ($ident == "HighTemp") {
            if ($value <= $this->GetValue("LowTemp") || $value > $this->ReadPropertyInteger("MaxTemp")) {
                return;
            }
        }

        if ($ident == "LowTemp") {
            if ($value >= $this->GetValue("HighTemp") || $value < $this->ReadPropertyInteger("MinTemp")) {
                return;
            }
        }

        if ($ident == "Heating") {
            if ($value == true) {
                $this->SetHeating(true);
                $this->WriteAttributeBoolean("Manual", $value);
                IPS_LogMessage("Heating", "Turn-On Heating Manualy");
            } else {
                $this->SetHeating(false);
                $this->WriteAttributeBoolean("Manual", $value);
                IPS_LogMessage("Heating", "Turn-On Heating Manualy");
            }
        }
        $this->SetValue($ident, $value);
    }


    /**
     * This Function will Monitor and Update the current Temp
     *
     * HT_MonitorTemp($id);
     *
     */
    public function MonitorTemp()
    {
        $this->UpdateCurrentTemp();

        $manual = $this->ReadAttributeBoolean("Manual");
        $heating = $this->GetValue("Heating");

        $currenttemp = $this->GetValue("CurrentTemp");

        $highTemp = $this->GetValue("HighTemp");
        $lowTemp = $this->GetValue("LowTemp");
        $maxtemp = $this->ReadPropertyInteger("MaxTemp");
        $minTemp = $this->ReadPropertyInteger("MinTemp");
        $currenthour = date("H");

        if ($currenttemp <= $minTemp) {
            if ($heating == false && $this->SetHeating(true)) {
                IPS_LogMessage("Heating", "WARNING Set Heating ON Buffer temp getting to Low");
                $this->SetValue("Heating", true);

                $message = "WARNING BUFFER TEMPERATUUR TO LOW ($currenttemp C)!";
                $this->SendTelegramNotification($message, 14400);
            } else {
                $message = "WARNING BUFFER TEMPERATUUR TO LOW ($currenttemp C) HEATING WAS ALLREADY TRUE OR ERROR SETTING!";
                $this->SendTelegramNotification($message, 14400);
            }
            return;
        }

        if ($currenttemp >= $maxtemp) {
            $message = "WARNING BUFFER TEMPERATUUR TO HIGH ($currenttemp C)!";
            $this->SendTelegramNotification($message, 120);

            if ($heating == true && $this->SetHeating(false)) {
                IPS_LogMessage("Heating", "WARNING Set Heating OFF Buffer temp getting to High");
                $this->SetValue("Heating", false);
            } else {
                $message = "WARNING BUFFER TEMPERATUUR TO HIGH ($currenttemp C) AND ERROR IN SWITCHING PELLETKACHEL!";
                $this->SendTelegramNotification($message, 120);
            }

            return;
        }

        if ($currenttemp >= $maxtemp && $heating == true) {
            $this->WriteAttributeBoolean("Manual", false);

            if ($this->SetHeating(false)) {
                IPS_LogMessage("Heating", "WARNING Set Heating OFF Buffer temp getting to High!");
                $this->SetValue("Heating", false);
            }
            return;
        }

        if ($currenttemp <= $lowTemp && $heating == false) {
            if ($currenthour >= 0 && $currenthour <= 5) {
                IPS_LogMessage("Heating", "Buffer below set temp, Prevent On in the night");
                return;
            } else if ($this->SetHeating(true)) {
                IPS_LogMessage("Heating", "Set Heating ON Buffer below set temp");
                $this->SetValue("Heating", true);
                return;
            }
        }

        if ($currenttemp >= $highTemp && $heating == true && $manual == false) {
            if ($this->SetHeating(false)) {
                IPS_LogMessage("Heating", "Set Heating OFF Buffer above set temp");
                $this->SetValue("Heating", false);
            }
            return;
        }
    }

    /**
     * This Function will Update the current Temp
     *
     * HT_MonitorTemp($id);
     *
     */
    public function UpdateCurrentTemp()
    {
        $this->SetValue("CurrentTemp", GetValueFloat($this->ReadPropertyInteger("BufferVar")));
    }


    function SetHeating(bool $state)
    {
        $IP = $this->ReadPropertyString("DomoticzIP");
        $Port = $this->ReadPropertyInteger("DomoticzPort");
        $idx = $this->ReadPropertyInteger("Domoticzidx");

        $action =  $state ? "On" : "Off";
        $req = "http://$IP:$Port/json.htm?type=command&param=switchlight&idx=$idx&switchcmd=$action";
        $response = file_get_contents($req);
        $response = json_decode($response, true);

        if ($response['status'] === "OK") {
            return true;
        } else {
            IPS_LogMessage("Heating", "ERROR Setting HEATING!!");
            return false;
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
