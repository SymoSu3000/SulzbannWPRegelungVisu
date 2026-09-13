<?php

declare(strict_types=1);

class SulzbannWPRegelungVisualisierung extends IPSModule
{
    /*
     * =====================================================================
     * SULZBANN
     * WP REGELUNG VISUALISIERUNG
     * =====================================================================
     *
     * REINE KONTROLLVISUALISIERUNG
     *
     * - keine KNX-Schreibzugriffe
     * - keine OZW-Schreibzugriffe
     * - keine eigene Heiz-/Kuehllogik
     *
     * Die Auswertung erfolgt im bestehenden Skript:
     *
     *     FBH Bedarfsauswertung #14256
     *
     * Diese Instanz visualisiert lediglich dessen Ergebnisse.
     *
     * =====================================================================
     */


    public function Create(): void
    {
        parent::Create();


        /*
         * -----------------------------------------------------------------
         * BASIS
         * -----------------------------------------------------------------
         */

        $this->RegisterPropertyInteger(
            'WPRegelungCategoryID',
            0
        );


        $this->RegisterPropertyInteger(
            'ParameterCategoryID',
            0
        );


        /*
         * -----------------------------------------------------------------
         * RENDERING
         * -----------------------------------------------------------------
         */

        $this->RegisterPropertyInteger(
            'RenderDelay',
            500
        );


        /*
         * -----------------------------------------------------------------
         * HTML
         * -----------------------------------------------------------------
         */

        $this->RegisterVariableString(
            'HTML',
            'WP Regelung',
            '~HTMLBox',
            10
        );


        /*
         * -----------------------------------------------------------------
         * TIMER
         * -----------------------------------------------------------------
         */

        $this->RegisterTimer(
            'RenderTimer',
            0,
            'SBWRV_Update($_IPS["TARGET"]);'
        );
    }


    public function ApplyChanges(): void
    {
        parent::ApplyChanges();


        /*
         * Alte Nachrichtenregistrierungen werden beim erneuten
         * ApplyChanges vom Kernel bereinigt.
         */

        foreach (
            $this->GetObservedVariableIDs()
            as $variableID
        ) {

            if (
                $variableID > 0
                &&
                IPS_VariableExists($variableID)
            ) {

                $this->RegisterMessage(
                    $variableID,
                    VM_UPDATE
                );
            }
        }


        $this->SetTimerInterval(
            'RenderTimer',
            0
        );


        $this->Update();
    }


    /*
     * =====================================================================
     * UPDATE
     * =====================================================================
     */

    public function Update(): void
    {
        $this->SetTimerInterval(
            'RenderTimer',
            0
        );


        $html =
            $this->BuildVisualization();


        $variableID =
            $this->GetIDForIdent(
                'HTML'
            );


        $currentHTML =
            GetValueString(
                $variableID
            );


        /*
         * Nur schreiben, wenn sich der Inhalt wirklich geändert hat.
         *
         * Damit vermeiden wir unnötige Neuladungen.
         */

        if (
            $currentHTML
            !==
            $html
        ) {

            SetValueString(
                $variableID,
                $html
            );
        }


        $this->SetStatus(
            102
        );
    }


    /*
     * =====================================================================
     * MESSAGESINK
     * =====================================================================
     */

    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ): void {

        parent::MessageSink(
            $TimeStamp,
            $SenderID,
            $Message,
            $Data
        );


        if (
            $Message
            !==
            VM_UPDATE
        ) {

            return;
        }


        $delay =
            max(
                250,
                $this->ReadPropertyInteger(
                    'RenderDelay'
                )
            );


        /*
         * Änderungen werden gesammelt.
         *
         * Wenn innerhalb kurzer Zeit mehrere Variablen aktualisiert werden,
         * wird nicht mehrfach gerendert.
         */

        $this->SetTimerInterval(
            'RenderTimer',
            $delay
        );
    }


    /*
     * =====================================================================
     * OBJEKTE FINDEN
     * =====================================================================
     */

    private function FindChildByName(
        int $parentID,
        string $name
    ): int {

        if (
            $parentID <= 0
            ||
            !IPS_ObjectExists($parentID)
        ) {

            return 0;
        }


        foreach (
            IPS_GetChildrenIDs($parentID)
            as $childID
        ) {

            if (
                IPS_GetName($childID)
                ===
                $name
            ) {

                return $childID;
            }
        }


        return 0;
    }


    /*
     * =====================================================================
     * WP-REGELUNG KATEGORIE
     * =====================================================================
     */

    private function GetWPRegelungCategoryID(): int
    {
        $configuredID =
            $this->ReadPropertyInteger(
                'WPRegelungCategoryID'
            );


        if (
            $configuredID > 0
            &&
            IPS_ObjectExists(
                $configuredID
            )
        ) {

            return $configuredID;
        }


        /*
         * Fallback:
         * Siemens OWZ #28320
         */

        $fallbackParentID =
            28320;


        return
            $this->FindChildByName(
                $fallbackParentID,
                'WP Regelung'
            );
    }


    /*
     * =====================================================================
     * PARAMETER-KATEGORIE
     * =====================================================================
     */

    private function GetParameterCategoryID(): int
    {
        $configuredID =
            $this->ReadPropertyInteger(
                'ParameterCategoryID'
            );


        if (
            $configuredID > 0
            &&
            IPS_ObjectExists(
                $configuredID
            )
        ) {

            return $configuredID;
        }


        $regelungID =
            $this->GetWPRegelungCategoryID();


        if ($regelungID <= 0) {

            return 0;
        }


        return
            $this->FindChildByName(
                $regelungID,
                'Parameter'
            );
    }


    /*
     * =====================================================================
     * VARIABLE NACH NAME
     * =====================================================================
     */

    private function GetVariableIDByName(
        string $name
    ): int {

        $regelungID =
            $this->GetWPRegelungCategoryID();


        if ($regelungID <= 0) {

            return 0;
        }


        $id =
            $this->FindChildByName(
                $regelungID,
                $name
            );


        if (
            $id <= 0
            ||
            !IPS_VariableExists($id)
        ) {

            return 0;
        }


        return $id;
    }


    private function GetParameterVariableIDByName(
        string $name
    ): int {

        $parameterID =
            $this->GetParameterCategoryID();


        if ($parameterID <= 0) {

            return 0;
        }


        $id =
            $this->FindChildByName(
                $parameterID,
                $name
            );


        if (
            $id <= 0
            ||
            !IPS_VariableExists($id)
        ) {

            return 0;
        }


        return $id;
    }


    /*
     * =====================================================================
     * SICHER LESEN
     * =====================================================================
     */

    private function ReadValueSafe(
        int $variableID,
        mixed $default = null
    ): mixed {

        if (
            $variableID <= 0
            ||
            !IPS_VariableExists(
                $variableID
            )
        ) {

            return $default;
        }


        try {

            return
                GetValue(
                    $variableID
                );

        } catch (Throwable $e) {

            return $default;
        }
    }


    private function ReadNamedValue(
        string $name,
        mixed $default = null
    ): mixed {

        return
            $this->ReadValueSafe(
                $this->GetVariableIDByName(
                    $name
                ),
                $default
            );
    }


    private function ReadParameter(
        string $name,
        mixed $default = null
    ): mixed {

        return
            $this->ReadValueSafe(
                $this->GetParameterVariableIDByName(
                    $name
                ),
                $default
            );
    }


    /*
     * =====================================================================
     * BEOBACHTETE VARIABLEN
     * =====================================================================
     */

    private function GetObservedVariableIDs(): array
    {
        $names = [

            'WP Betriebsart aktuell',

            'Heizregelung aktiv',
            'Kuehlregelung aktiv',

            'Raumtemperatur Mittel',
            'Raumtemperatur Minimum',
            'Raumtemperatur Maximum',
            'Raumtemperatur Trend',

            'Heizbedarf Gebaeude Rohwert',
            'Kuehlbedarf Gebaeude Rohwert',

            'Heizbedarf steuerrelevant',
            'Kuehlbedarf steuerrelevant',

            'Bedarf passend zur WP Betriebsart',

            'Raeume mit Heizbedarf',
            'Raeume mit Kuehlbedarf',

            'FBH Ventile offen Gesamt',
            'FBH Ventile offen EG',
            'FBH Ventile offen OG',
            'FBH Ventile offen Einliegerwohnung',

            'FBH Stellwert Mittel',
            'FBH Stellwert Maximum',

            'FBH Hydraulik verfuegbar',

            'WP Optimierung erlaubt',

            'Prognose morgen Temperatur Maximum',
            'Prognose morgen Temperatur Minimum',
            'Prognose morgen Temperatur Mittel',
            'Prognose morgen Globalstrahlung',

            'Prognose Heizbedarf Rohwert',
            'Prognose Kuehlbedarf Rohwert',

            'Heizprognose steuerrelevant',
            'Kuehlprognose steuerrelevant',

            'PV Prognose 0-24h P50',
            'PV Prognose 24-48h P50',
            'PV Prognose Peak 0-24h',
            'PV Prognose Vertrauen 0-24h',
            'PV Ertrag gut erwartet',

            'Prognose passend zur WP Betriebsart',

            'Energie Vorziehen sinnvoll',

            'Bedarfsstatus',
            'Prognosestatus'

        ];


        $ids = [];


        foreach (
            $names
            as $name
        ) {

            $id =
                $this->GetVariableIDByName(
                    $name
                );


            if ($id > 0) {

                $ids[] =
                    $id;
            }
        }


        /*
         * Parameter ebenfalls beobachten.
         */

        $parameterNames = [

            'Heizen EIN unter Raumsollwert',
            'Heizen AUS unter Raumsollwert',

            'Kuehlen EIN Raumtemperatur',
            'Kuehlen AUS Raumtemperatur',

            'Prognose Heizgrenze Tagesmittel',
            'Prognose Heizgrenze Minimum',

            'Prognose Kuehlgrenze Maximum',
            'Prognose starke Globalstrahlung',

            'Solcast gute PV 0-24h',
            'Solcast gute PV 24-48h',
            'Solcast Mindestvertrauen'

        ];


        foreach (
            $parameterNames
            as $name
        ) {

            $id =
                $this->GetParameterVariableIDByName(
                    $name
                );


            if ($id > 0) {

                $ids[] =
                    $id;
            }
        }


        return
            array_values(
                array_unique(
                    $ids
                )
            );
    }


    /*
     * =====================================================================
     * FORMATIERUNG
     * =====================================================================
     */

    private function H(
        mixed $value
    ): string {

        return
            htmlspecialchars(
                (string) $value,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            );
    }


    private function BoolText(
        bool $value
    ): string {

        return
            $value
                ?
                'JA'
                :
                'NEIN';
    }


    private function BoolClass(
        bool $value
    ): string {

        return
            $value
                ?
                'yes'
                :
                'no';
    }


    private function Number(
        mixed $value,
        int $digits = 1
    ): string {

        if (!is_numeric($value)) {

            return '—';
        }


        return
            number_format(
                (float) $value,
                $digits,
                '.',
                ''
            );
    }


    /*
     * =====================================================================
     * TEMPLATE
     * =====================================================================
     */

    private function LoadTemplate(): string
    {
        $file =
            __DIR__
            .
            '/module.html';


        if (!is_file($file)) {

            return
                '<div style="padding:20px;color:red;">'
                .
                'module.html fehlt'
                .
                '</div>';
        }


        $html =
            file_get_contents(
                $file
            );


        if ($html === false) {

            return
                '<div style="padding:20px;color:red;">'
                .
                'module.html konnte nicht geladen werden'
                .
                '</div>';
        }


        return $html;
    }


    /*
     * =====================================================================
     * BUILD
     * =====================================================================
     */

    private function BuildVisualization(): string
    {
        $template =
            $this->LoadTemplate();


        /*
         * -----------------------------------------------------------------
         * WP
         * -----------------------------------------------------------------
         */

        $mode =
            (string) $this->ReadNamedValue(
                'WP Betriebsart aktuell',
                'Unbekannt'
            );


        $heatingActive =
            (bool) $this->ReadNamedValue(
                'Heizregelung aktiv',
                false
            );


        $coolingActive =
            (bool) $this->ReadNamedValue(
                'Kuehlregelung aktiv',
                false
            );


        /*
         * -----------------------------------------------------------------
         * RAUM
         * -----------------------------------------------------------------
         */

        $tempMean =
            $this->ReadNamedValue(
                'Raumtemperatur Mittel',
                null
            );


        $tempMin =
            $this->ReadNamedValue(
                'Raumtemperatur Minimum',
                null
            );


        $tempMax =
            $this->ReadNamedValue(
                'Raumtemperatur Maximum',
                null
            );


        $tempTrend =
            $this->ReadNamedValue(
                'Raumtemperatur Trend',
                null
            );


        /*
         * -----------------------------------------------------------------
         * BEDARF
         * -----------------------------------------------------------------
         */

        $heatRaw =
            (bool) $this->ReadNamedValue(
                'Heizbedarf Gebaeude Rohwert',
                false
            );


        $coolRaw =
            (bool) $this->ReadNamedValue(
                'Kuehlbedarf Gebaeude Rohwert',
                false
            );


        $heatRelevant =
            (bool) $this->ReadNamedValue(
                'Heizbedarf steuerrelevant',
                false
            );


        $coolRelevant =
            (bool) $this->ReadNamedValue(
                'Kuehlbedarf steuerrelevant',
                false
            );


        $relevantDemand =
            (bool) $this->ReadNamedValue(
                'Bedarf passend zur WP Betriebsart',
                false
            );


        $heatRooms =
            (int) $this->ReadNamedValue(
                'Raeume mit Heizbedarf',
                0
            );


        $coolRooms =
            (int) $this->ReadNamedValue(
                'Raeume mit Kuehlbedarf',
                0
            );


        /*
         * -----------------------------------------------------------------
         * FBH
         * -----------------------------------------------------------------
         */

        $openTotal =
            (int) $this->ReadNamedValue(
                'FBH Ventile offen Gesamt',
                0
            );


        $openEG =
            (int) $this->ReadNamedValue(
                'FBH Ventile offen EG',
                0
            );


        $openOG =
            (int) $this->ReadNamedValue(
                'FBH Ventile offen OG',
                0
            );


        $openELW =
            (int) $this->ReadNamedValue(
                'FBH Ventile offen Einliegerwohnung',
                0
            );


        $demandMean =
            $this->ReadNamedValue(
                'FBH Stellwert Mittel',
                null
            );


        $demandMax =
            $this->ReadNamedValue(
                'FBH Stellwert Maximum',
                null
            );


        $hydraulic =
            (bool) $this->ReadNamedValue(
                'FBH Hydraulik verfuegbar',
                false
            );


        $optimization =
            (bool) $this->ReadNamedValue(
                'WP Optimierung erlaubt',
                false
            );


        /*
         * -----------------------------------------------------------------
         * METEO
         * -----------------------------------------------------------------
         */

        $forecastMax =
            $this->ReadNamedValue(
                'Prognose morgen Temperatur Maximum',
                null
            );


        $forecastMean =
            $this->ReadNamedValue(
                'Prognose morgen Temperatur Mittel',
                null
            );


        $forecastMin =
            $this->ReadNamedValue(
                'Prognose morgen Temperatur Minimum',
                null
            );


        $forecastSolar =
            $this->ReadNamedValue(
                'Prognose morgen Globalstrahlung',
                null
            );


        $forecastHeatRaw =
            (bool) $this->ReadNamedValue(
                'Prognose Heizbedarf Rohwert',
                false
            );


        $forecastCoolRaw =
            (bool) $this->ReadNamedValue(
                'Prognose Kuehlbedarf Rohwert',
                false
            );


        $forecastHeatRelevant =
            (bool) $this->ReadNamedValue(
                'Heizprognose steuerrelevant',
                false
            );


        $forecastCoolRelevant =
            (bool) $this->ReadNamedValue(
                'Kuehlprognose steuerrelevant',
                false
            );


        /*
         * -----------------------------------------------------------------
         * PV
         * -----------------------------------------------------------------
         */

        $pv024 =
            $this->ReadNamedValue(
                'PV Prognose 0-24h P50',
                null
            );


        $pv2448 =
            $this->ReadNamedValue(
                'PV Prognose 24-48h P50',
                null
            );


        $pvPeak =
            $this->ReadNamedValue(
                'PV Prognose Peak 0-24h',
                null
            );


        $pvConfidence =
            $this->ReadNamedValue(
                'PV Prognose Vertrauen 0-24h',
                null
            );


        $pvGood =
            (bool) $this->ReadNamedValue(
                'PV Ertrag gut erwartet',
                false
            );


        $forecastRelevant =
            (bool) $this->ReadNamedValue(
                'Prognose passend zur WP Betriebsart',
                false
            );


        $preShift =
            (bool) $this->ReadNamedValue(
                'Energie Vorziehen sinnvoll',
                false
            );


        /*
         * -----------------------------------------------------------------
         * STATUS
         * -----------------------------------------------------------------
         */

        $bedarfStatus =
            (string) $this->ReadNamedValue(
                'Bedarfsstatus',
                '—'
            );


        $forecastStatus =
            (string) $this->ReadNamedValue(
                'Prognosestatus',
                '—'
            );


        /*
         * -----------------------------------------------------------------
         * PARAMETER
         * -----------------------------------------------------------------
         */

        $heatOn =
            $this->ReadParameter(
                'Heizen EIN unter Raumsollwert',
                null
            );


        $heatOff =
            $this->ReadParameter(
                'Heizen AUS unter Raumsollwert',
                null
            );


        $coolOn =
            $this->ReadParameter(
                'Kuehlen EIN Raumtemperatur',
                null
            );


        $coolOff =
            $this->ReadParameter(
                'Kuehlen AUS Raumtemperatur',
                null
            );


        $forecastHeatMean =
            $this->ReadParameter(
                'Prognose Heizgrenze Tagesmittel',
                null
            );


        $forecastHeatMin =
            $this->ReadParameter(
                'Prognose Heizgrenze Minimum',
                null
            );


        $forecastCoolMax =
            $this->ReadParameter(
                'Prognose Kuehlgrenze Maximum',
                null
            );


        $forecastSolarLimit =
            $this->ReadParameter(
                'Prognose starke Globalstrahlung',
                null
            );


        $pvLimit =
            $this->ReadParameter(
                'Solcast gute PV 0-24h',
                null
            );


        $confidenceLimit =
            $this->ReadParameter(
                'Solcast Mindestvertrauen',
                null
            );


        /*
         * -----------------------------------------------------------------
         * TREND
         * -----------------------------------------------------------------
         */

        $trendText =
            'stabil';


        if (
            is_numeric($tempTrend)
            &&
            (float) $tempTrend > 0.05
        ) {

            $trendText =
                'steigend';

        } elseif (
            is_numeric($tempTrend)
            &&
            (float) $tempTrend < -0.05
        ) {

            $trendText =
                'fallend';
        }


        /*
         * -----------------------------------------------------------------
         * MODUSKLASSE
         * -----------------------------------------------------------------
         */

        $modeClass =
            'standby';


        switch ($mode) {

            case 'Heizen':

                $modeClass =
                    'heating';

                break;


            case 'Kuehlen':

                $modeClass =
                    'cooling';

                break;


            case 'Unplausibel':

                $modeClass =
                    'warning';

                break;
        }


        /*
         * -----------------------------------------------------------------
         * PARAMETER-KATEGORIE FUER BUTTON
         * -----------------------------------------------------------------
         */

        $parameterCategoryID =
            $this->GetParameterCategoryID();


        /*
         * -----------------------------------------------------------------
         * PLACEHOLDER
         * -----------------------------------------------------------------
         */

        $replace = [

            '{{MODE}}' =>
                $this->H(
                    $mode
                ),

            '{{MODE_CLASS}}' =>
                $modeClass,


            '{{HEATING_ACTIVE}}' =>
                $this->BoolText(
                    $heatingActive
                ),

            '{{HEATING_ACTIVE_CLASS}}' =>
                $this->BoolClass(
                    $heatingActive
                ),


            '{{COOLING_ACTIVE}}' =>
                $this->BoolText(
                    $coolingActive
                ),

            '{{COOLING_ACTIVE_CLASS}}' =>
                $this->BoolClass(
                    $coolingActive
                ),


            '{{TEMP_MEAN}}' =>
                $this->Number(
                    $tempMean,
                    1
                ),

            '{{TEMP_MIN}}' =>
                $this->Number(
                    $tempMin,
                    1
                ),

            '{{TEMP_MAX}}' =>
                $this->Number(
                    $tempMax,
                    1
                ),

            '{{TEMP_TREND}}' =>
                $this->Number(
                    $tempTrend,
                    2
                ),

            '{{TEMP_TREND_TEXT}}' =>
                $this->H(
                    $trendText
                ),


            '{{HEAT_RAW}}' =>
                $this->BoolText(
                    $heatRaw
                ),

            '{{HEAT_RAW_CLASS}}' =>
                $this->BoolClass(
                    $heatRaw
                ),

            '{{HEAT_RELEVANT}}' =>
                $this->BoolText(
                    $heatRelevant
                ),

            '{{HEAT_RELEVANT_CLASS}}' =>
                $this->BoolClass(
                    $heatRelevant
                ),


            '{{COOL_RAW}}' =>
                $this->BoolText(
                    $coolRaw
                ),

            '{{COOL_RAW_CLASS}}' =>
                $this->BoolClass(
                    $coolRaw
                ),

            '{{COOL_RELEVANT}}' =>
                $this->BoolText(
                    $coolRelevant
                ),

            '{{COOL_RELEVANT_CLASS}}' =>
                $this->BoolClass(
                    $coolRelevant
                ),


            '{{RELEVANT_DEMAND}}' =>
                $this->BoolText(
                    $relevantDemand
                ),

            '{{RELEVANT_DEMAND_CLASS}}' =>
                $this->BoolClass(
                    $relevantDemand
                ),


            '{{HEAT_ROOMS}}' =>
                (string) $heatRooms,

            '{{COOL_ROOMS}}' =>
                (string) $coolRooms,


            '{{OPEN_TOTAL}}' =>
                (string) $openTotal,

            '{{OPEN_EG}}' =>
                (string) $openEG,

            '{{OPEN_OG}}' =>
                (string) $openOG,

            '{{OPEN_ELW}}' =>
                (string) $openELW,


            '{{DEMAND_MEAN}}' =>
                $this->Number(
                    $demandMean,
                    1
                ),

            '{{DEMAND_MAX}}' =>
                $this->Number(
                    $demandMax,
                    1
                ),


            '{{HYDRAULIC}}' =>
                $this->BoolText(
                    $hydraulic
                ),

            '{{HYDRAULIC_CLASS}}' =>
                $this->BoolClass(
                    $hydraulic
                ),


            '{{OPTIMIZATION}}' =>
                $this->BoolText(
                    $optimization
                ),

            '{{OPTIMIZATION_CLASS}}' =>
                $this->BoolClass(
                    $optimization
                ),


            '{{FORECAST_MAX}}' =>
                $this->Number(
                    $forecastMax,
                    1
                ),

            '{{FORECAST_MEAN}}' =>
                $this->Number(
                    $forecastMean,
                    1
                ),

            '{{FORECAST_MIN}}' =>
                $this->Number(
                    $forecastMin,
                    1
                ),

            '{{FORECAST_SOLAR}}' =>
                $this->Number(
                    $forecastSolar,
                    2
                ),


            '{{FORECAST_HEAT_RAW}}' =>
                $this->BoolText(
                    $forecastHeatRaw
                ),

            '{{FORECAST_HEAT_RAW_CLASS}}' =>
                $this->BoolClass(
                    $forecastHeatRaw
                ),

            '{{FORECAST_HEAT_RELEVANT}}' =>
                $this->BoolText(
                    $forecastHeatRelevant
                ),

            '{{FORECAST_HEAT_RELEVANT_CLASS}}' =>
                $this->BoolClass(
                    $forecastHeatRelevant
                ),


            '{{FORECAST_COOL_RAW}}' =>
                $this->BoolText(
                    $forecastCoolRaw
                ),

            '{{FORECAST_COOL_RAW_CLASS}}' =>
                $this->BoolClass(
                    $forecastCoolRaw
                ),

            '{{FORECAST_COOL_RELEVANT}}' =>
                $this->BoolText(
                    $forecastCoolRelevant
                ),

            '{{FORECAST_COOL_RELEVANT_CLASS}}' =>
                $this->BoolClass(
                    $forecastCoolRelevant
                ),


            '{{FORECAST_RELEVANT}}' =>
                $this->BoolText(
                    $forecastRelevant
                ),

            '{{FORECAST_RELEVANT_CLASS}}' =>
                $this->BoolClass(
                    $forecastRelevant
                ),


            '{{PV024}}' =>
                $this->Number(
                    $pv024,
                    1
                ),

            '{{PV2448}}' =>
                $this->Number(
                    $pv2448,
                    1
                ),

            '{{PV_PEAK}}' =>
                $this->Number(
                    $pvPeak,
                    2
                ),

            '{{PV_CONFIDENCE}}' =>
                $this->Number(
                    $pvConfidence,
                    1
                ),


            '{{PV_GOOD}}' =>
                $this->BoolText(
                    $pvGood
                ),

            '{{PV_GOOD_CLASS}}' =>
                $this->BoolClass(
                    $pvGood
                ),


            '{{PRESHIFT}}' =>
                $this->BoolText(
                    $preShift
                ),

            '{{PRESHIFT_CLASS}}' =>
                $this->BoolClass(
                    $preShift
                ),


            '{{HEAT_ON}}' =>
                $this->Number(
                    $heatOn,
                    1
                ),

            '{{HEAT_OFF}}' =>
                $this->Number(
                    $heatOff,
                    1
                ),

            '{{COOL_ON}}' =>
                $this->Number(
                    $coolOn,
                    1
                ),

            '{{COOL_OFF}}' =>
                $this->Number(
                    $coolOff,
                    1
                ),

            '{{FORECAST_HEAT_MEAN}}' =>
                $this->Number(
                    $forecastHeatMean,
                    1
                ),

            '{{FORECAST_HEAT_MIN}}' =>
                $this->Number(
                    $forecastHeatMin,
                    1
                ),

            '{{FORECAST_COOL_MAX}}' =>
                $this->Number(
                    $forecastCoolMax,
                    1
                ),

            '{{FORECAST_SOLAR_LIMIT}}' =>
                $this->Number(
                    $forecastSolarLimit,
                    2
                ),

            '{{PV_LIMIT}}' =>
                $this->Number(
                    $pvLimit,
                    1
                ),

            '{{CONFIDENCE_LIMIT}}' =>
                $this->Number(
                    $confidenceLimit,
                    1
                ),


            '{{BEDARF_STATUS}}' =>
                $this->H(
                    $bedarfStatus
                ),

            '{{FORECAST_STATUS}}' =>
                $this->H(
                    $forecastStatus
                ),


            '{{PARAMETER_CATEGORY_ID}}' =>
                (string) $parameterCategoryID

        ];


        return
            strtr(
                $template,
                $replace
            );
    }
}
