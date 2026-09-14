<?php

declare(strict_types=1);

class SulzbannWPRegelungVisu extends IPSModule
{
    /*
     * ============================================================
     * SULZBANN WP REGELUNG VISUALISIERUNG
     * ============================================================
     *
     * Architektur bewusst 1:1 nach der funktionierenden
     * Sulzbann Heizung Visualisierung:
     *
     * - eine HTMLBox
     * - Compact + Detail in derselben module.html
     * - Umschaltung über Kachelhöhe
     * - VM_UPDATE -> RenderTimer -> Update()
     * - nur bei verändertem HTML SetValueString()
     *
     * KEIN:
     * - HTML-SDK
     * - SetVisualizationType()
     * - separates Grossmodul
     * - WebContent-Zwischenobjekt
     * - openObject()
     * - UpdateVisualizationValue()
     *
     * ============================================================
     */

    private const SETTINGS_OBJECT_ID = 26699;

    /*
     * ============================================================
     * WP / OZW
     * ============================================================
     */

    private const WP_HEATING_ID = 44357;
    private const WP_COOLING_ID = 17689;

    private const OUTSIDE_TEMP_ID = 50237;
    private const WP_FLOW_TEMP_ID = 27923;
    private const WP_RETURN_TEMP_ID = 45419;

    private const WP_POWER_ID = 50450;
    private const WP_HEAT_OUTPUT_ID = 32403;
    private const WP_COP_ID = 37700;
    private const WP_MODULATION_ID = 20837;

    /*
     * ============================================================
     * SPEICHER
     * ============================================================
     */

    private const BUFFER_TOP_ID = 27553;
    private const BUFFER_MIDDLE_ID = 52270;
    private const BUFFER_BOTTOM_ID = 18594;

    private const DHW_TOP_ID = 39112;
    private const DHW_BOTTOM_ID = 40096;

    /*
     * ============================================================
     * METEO TAG 2 / MORGEN
     * ============================================================
     */

    private const METEO_MAX_ID = 42622;
    private const METEO_MEAN_ID = 11157;
    private const METEO_MIN_ID = 28499;
    private const METEO_SOLAR_ID = 36980;

    /*
     * ============================================================
     * SOLCAST
     * ============================================================
     */

    private const SOLCAST_PARENT_ID = 16397;

    /*
     * ============================================================
     * RAUMTEMPERATUREN
     * ============================================================
     */

    private const ROOM_TEMP_IDS = [
        // EG
        47619,
        43486,
        20056,
        59980,

        // OG
        52271,
        51361,
        43453,
        16944,
        26539,
        12860,

        // ELW
        32416,
        25829,
        45373,
        29653
    ];

    /*
     * ============================================================
     * FBH VENTILSTATUS
     *
     * physikalisch geprüft:
     * false / 0 = geschlossen
     * true  / 1 = offen
     * ============================================================
     */

    private const VALVE_STATE_IDS = [
        // EG
        18344,
        50892,
        37309,
        57721,

        // OG
        52749,
        46861,
        49967,
        15602,
        27850,
        10375,

        // ELW
        33924,
        19131,
        58629,
        12822
    ];

    /*
     * ============================================================
     * FBH STELLWERTE
     *
     * MDT PWM-Bedarf 0...100 %
     * ============================================================
     */

    private const VALVE_DEMAND_IDS = [
        // EG
        39391,
        22281,
        49588,
        51260,

        // OG
        26389,
        43578,
        36459,
        26819,
        39276,
        16937,

        // ELW
        19263,
        17140,
        14321,
        20806
    ];

    /*
     * ============================================================
     * CREATE
     * ============================================================
     */

    public function Create(): void
    {
        parent::Create();

        /*
         * Genau wie bei der funktionierenden Heizungsvisu:
         * sichtbarer Inhalt ist eine HTMLBox-Kindvariable.
         */
        $this->RegisterVariableString(
            'HTML',
            'WP Regelung',
            '~HTMLBox',
            10
        );

        /*
         * Änderungen werden gesammelt.
         * Dadurch kein Rendern bei jedem einzelnen VM_UPDATE.
         */
        $this->RegisterPropertyInteger(
            'RenderDelay',
            800
        );

        $this->RegisterTimer(
            'RenderTimer',
            0,
            'SBWRV_Update($_IPS["TARGET"]);'
        );
    }

    /*
     * ============================================================
     * APPLY CHANGES
     * ============================================================
     */

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

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
     * ============================================================
     * UPDATE
     * ============================================================
     */

    public function Update(): void
    {
        /*
         * Timer sofort wieder stoppen.
         */
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

        if (
            $variableID <= 0
            ||
            !IPS_VariableExists($variableID)
        ) {
            return;
        }

        $current =
            GetValueString(
                $variableID
            );

        /*
         * Wichtig:
         * nur schreiben, wenn sich das HTML wirklich geändert hat.
         */
        if ($current !== $html) {
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
     * ============================================================
     * MESSAGE SINK
     * ============================================================
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

        if ($Message !== VM_UPDATE) {
            return;
        }

        /*
         * Mehrere Änderungen innerhalb kurzer Zeit zusammenfassen.
         */
        $delay =
            max(
                250,
                $this->ReadPropertyInteger(
                    'RenderDelay'
                )
            );

        $this->SetTimerInterval(
            'RenderTimer',
            $delay
        );
    }

    /*
     * ============================================================
     * BEOBACHTETE VARIABLEN
     * ============================================================
     */

    private function GetObservedVariableIDs(): array
    {
        $ids = [
            /*
             * WP
             */
            self::WP_HEATING_ID,
            self::WP_COOLING_ID,

            self::OUTSIDE_TEMP_ID,
            self::WP_FLOW_TEMP_ID,
            self::WP_RETURN_TEMP_ID,

            self::WP_POWER_ID,
            self::WP_HEAT_OUTPUT_ID,
            self::WP_COP_ID,
            self::WP_MODULATION_ID,

            /*
             * Speicher
             */
            self::BUFFER_TOP_ID,
            self::BUFFER_MIDDLE_ID,
            self::BUFFER_BOTTOM_ID,

            self::DHW_TOP_ID,
            self::DHW_BOTTOM_ID,

            /*
             * Wetter
             */
            self::METEO_MAX_ID,
            self::METEO_MEAN_ID,
            self::METEO_MIN_ID,
            self::METEO_SOLAR_ID
        ];

        /*
         * Räume
         */
        foreach (
            self::ROOM_TEMP_IDS
            as $id
        ) {
            $ids[] = $id;
        }

        /*
         * Ventile
         */
        foreach (
            self::VALVE_STATE_IDS
            as $id
        ) {
            $ids[] = $id;
        }

        /*
         * Stellwerte
         */
        foreach (
            self::VALVE_DEMAND_IDS
            as $id
        ) {
            $ids[] = $id;
        }

        /*
         * Solcast dynamisch über Ident.
         */
        foreach (
            $this->GetSolcastVariableIDs()
            as $id
        ) {
            $ids[] = $id;
        }

        return array_values(
            array_unique(
                $ids
            )
        );
    }

    /*
     * ============================================================
     * SOLCAST IDs
     * ============================================================
     */

    private function GetSolcastVariableIDs(): array
    {
        $result = [];

        foreach (
            [
                'SolcastEnergy024P50',
                'SolcastPeak024',
                'SolcastConfidence024',
                'SolcastPeakTime024',
                'SolcastPeakWindowStart024',
                'SolcastPeakWindowEnd024'
            ]
            as $ident
        ) {
            $id =
                $this->GetSolcastVariableID(
                    $ident
                );

            if ($id > 0) {
                $result[] = $id;
            }
        }

        return $result;
    }

    private function GetSolcastVariableID(
        string $ident
    ): int {
        if (
            !IPS_ObjectExists(
                self::SOLCAST_PARENT_ID
            )
        ) {
            return 0;
        }

        $id =
            @IPS_GetObjectIDByIdent(
                $ident,
                self::SOLCAST_PARENT_ID
            );

        if (
            $id === false
            ||
            !IPS_VariableExists(
                (int) $id
            )
        ) {
            return 0;
        }

        return (int) $id;
    }

    /*
     * ============================================================
     * SICHER LESEN
     * ============================================================
     */

    private function ReadValueSafe(
        int $variableID,
        mixed $default = null
    ): mixed {
        if (
            $variableID <= 0
            ||
            !IPS_VariableExists($variableID)
        ) {
            return $default;
        }

        try {
            return GetValue(
                $variableID
            );

        } catch (Throwable $e) {
            return $default;
        }
    }

    private function ReadFloat(
        int $variableID
    ): ?float {
        $value =
            $this->ReadValueSafe(
                $variableID,
                null
            );

        if (
            $value === null
            ||
            !is_numeric($value)
        ) {
            return null;
        }

        return (float) $value;
    }

    private function ReadBool(
        int $variableID
    ): bool {
        $value =
            $this->ReadValueSafe(
                $variableID,
                false
            );

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return
                ((float) $value)
                !==
                0.0;
        }

        return false;
    }

    private function ReadSolcastFloat(
        string $ident
    ): ?float {
        $id =
            $this->GetSolcastVariableID(
                $ident
            );

        if ($id <= 0) {
            return null;
        }

        return $this->ReadFloat(
            $id
        );
    }

    private function ReadSolcastInteger(
        string $ident
    ): int {
        $id =
            $this->GetSolcastVariableID(
                $ident
            );

        if ($id <= 0) {
            return 0;
        }

        $value =
            $this->ReadValueSafe(
                $id,
                0
            );

        if (!is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }

    /*
     * ============================================================
     * AUSWERTUNG
     * ============================================================
     */

    private function BuildData(): array
    {
        /*
         * --------------------------------------------------------
         * WP IST MASTER
         * --------------------------------------------------------
         */

        $heating =
            $this->ReadBool(
                self::WP_HEATING_ID
            );

        $cooling =
            $this->ReadBool(
                self::WP_COOLING_ID
            );

        if (
            $heating
            &&
            !$cooling
        ) {
            $mode =
                'Heizen';

            $modeClass =
                'heating';

        } elseif (
            $cooling
            &&
            !$heating
        ) {
            $mode =
                'Kühlen';

            $modeClass =
                'cooling';

        } elseif (
            !$heating
            &&
            !$cooling
        ) {
            $mode =
                'Standby';

            $modeClass =
                'standby';

        } else {
            $mode =
                'Unplausibel';

            $modeClass =
                'fault';
        }

        /*
         * --------------------------------------------------------
         * RAUMTEMPERATUREN
         * --------------------------------------------------------
         */

        $roomValues = [];

        foreach (
            self::ROOM_TEMP_IDS
            as $id
        ) {
            $value =
                $this->ReadFloat(
                    $id
                );

            if (
                $value !== null
                &&
                $value > -30
                &&
                $value < 60
            ) {
                $roomValues[] =
                    $value;
            }
        }

        $roomAverage = null;
        $roomMinimum = null;
        $roomMaximum = null;

        if (
            count($roomValues) > 0
        ) {
            $roomAverage =
                array_sum(
                    $roomValues
                )
                /
                count(
                    $roomValues
                );

            $roomMinimum =
                min(
                    $roomValues
                );

            $roomMaximum =
                max(
                    $roomValues
                );
        }

        /*
         * --------------------------------------------------------
         * VENTILE
         * --------------------------------------------------------
         */

        $openValves = 0;

        foreach (
            self::VALVE_STATE_IDS
            as $id
        ) {
            if (
                $this->ReadBool($id)
            ) {
                $openValves++;
            }
        }

        /*
         * --------------------------------------------------------
         * STELLWERTE
         * --------------------------------------------------------
         */

        $demands = [];

        foreach (
            self::VALVE_DEMAND_IDS
            as $id
        ) {
            $value =
                $this->ReadFloat(
                    $id
                );

            if ($value !== null) {
                $demands[] =
                    max(
                        0.0,
                        min(
                            100.0,
                            $value
                        )
                    );
            }
        }

        $demandAverage = null;
        $demandMaximum = null;

        if (
            count($demands) > 0
        ) {
            $demandAverage =
                array_sum(
                    $demands
                )
                /
                count(
                    $demands
                );

            $demandMaximum =
                max(
                    $demands
                );
        }

        return [
            'mode' =>
                $mode,

            'modeClass' =>
                $modeClass,

            'outside' =>
                $this->ReadFloat(
                    self::OUTSIDE_TEMP_ID
                ),

            'flow' =>
                $this->ReadFloat(
                    self::WP_FLOW_TEMP_ID
                ),

            'return' =>
                $this->ReadFloat(
                    self::WP_RETURN_TEMP_ID
                ),

            'power' =>
                $this->ReadFloat(
                    self::WP_POWER_ID
                ),

            'heatOutput' =>
                $this->ReadFloat(
                    self::WP_HEAT_OUTPUT_ID
                ),

            'cop' =>
                $this->ReadFloat(
                    self::WP_COP_ID
                ),

            'modulation' =>
                $this->ReadFloat(
                    self::WP_MODULATION_ID
                ),

            'roomsCount' =>
                count(
                    $roomValues
                ),

            'roomsAverage' =>
                $roomAverage,

            'roomsMinimum' =>
                $roomMinimum,

            'roomsMaximum' =>
                $roomMaximum,

            'openValves' =>
                $openValves,

            'totalValves' =>
                count(
                    self::VALVE_STATE_IDS
                ),

            'demandAverage' =>
                $demandAverage,

            'demandMaximum' =>
                $demandMaximum,

            'dhwTop' =>
                $this->ReadFloat(
                    self::DHW_TOP_ID
                ),

            'dhwBottom' =>
                $this->ReadFloat(
                    self::DHW_BOTTOM_ID
                ),

            'bufferTop' =>
                $this->ReadFloat(
                    self::BUFFER_TOP_ID
                ),

            'bufferMiddle' =>
                $this->ReadFloat(
                    self::BUFFER_MIDDLE_ID
                ),

            'bufferBottom' =>
                $this->ReadFloat(
                    self::BUFFER_BOTTOM_ID
                ),

            'meteoMax' =>
                $this->ReadFloat(
                    self::METEO_MAX_ID
                ),

            'meteoMean' =>
                $this->ReadFloat(
                    self::METEO_MEAN_ID
                ),

            'meteoMin' =>
                $this->ReadFloat(
                    self::METEO_MIN_ID
                ),

            'meteoSolar' =>
                $this->ReadFloat(
                    self::METEO_SOLAR_ID
                ),

            'solcastEnergy' =>
                $this->ReadSolcastFloat(
                    'SolcastEnergy024P50'
                ),

            'solcastPeak' =>
                $this->ReadSolcastFloat(
                    'SolcastPeak024'
                ),

            'solcastConfidence' =>
                $this->ReadSolcastFloat(
                    'SolcastConfidence024'
                ),

            'solcastPeakTime' =>
                $this->ReadSolcastInteger(
                    'SolcastPeakTime024'
                ),

            'solcastWindowStart' =>
                $this->ReadSolcastInteger(
                    'SolcastPeakWindowStart024'
                ),

            'solcastWindowEnd' =>
                $this->ReadSolcastInteger(
                    'SolcastPeakWindowEnd024'
                ),

            'timestamp' =>
                time()
        ];
    }

    /*
     * ============================================================
     * TEMPLATE
     * ============================================================
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
     * ============================================================
     * BUILD VISUALIZATION
     * ============================================================
     */

    private function BuildVisualization(): string
    {
        $template =
            $this->LoadTemplate();

        $d =
            $this->BuildData();

        $roomRange =
            (
                $d['roomsMinimum'] !== null
                &&
                $d['roomsMaximum'] !== null
            )
                ?
                number_format(
                    (float) $d['roomsMinimum'],
                    1,
                    '.',
                    ''
                )
                .
                ' / '
                .
                number_format(
                    (float) $d['roomsMaximum'],
                    1,
                    '.',
                    ''
                )
                .
                ' °C'
                :
                '—';

        $valves =
            (int) $d['openValves']
            .
            ' / '
            .
            (int) $d['totalValves'];

        $peakWindow =
            (
                $d['solcastWindowStart'] > 0
                &&
                $d['solcastWindowEnd'] > 0
            )
                ?
                $this->FormatTime(
                    $d['solcastWindowStart']
                )
                .
                ' – '
                .
                $this->FormatTime(
                    $d['solcastWindowEnd']
                )
                :
                '—';

        $replace = [
            '{{MODE}}' =>
                $this->H(
                    $d['mode']
                ),

            '{{MODE_CLASS}}' =>
                $this->H(
                    $d['modeClass']
                ),

            '{{OUTSIDE}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['outside']
                    )
                ),

            '{{FLOW}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['flow']
                    )
                ),

            '{{RETURN}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['return']
                    )
                ),

            '{{POWER}}' =>
                $this->H(
                    $this->FormatPower(
                        $d['power']
                    )
                ),

            '{{HEAT_OUTPUT}}' =>
                $this->H(
                    $this->FormatPower(
                        $d['heatOutput']
                    )
                ),

            '{{COP}}' =>
                $this->H(
                    $this->FormatCOP(
                        $d['cop']
                    )
                ),

            '{{MODULATION}}' =>
                $this->H(
                    $this->FormatPercent(
                        $d['modulation']
                    )
                ),

            '{{ROOM_COUNT}}' =>
                (string) $d['roomsCount'],

            '{{ROOM_AVERAGE}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['roomsAverage']
                    )
                ),

            '{{ROOM_RANGE}}' =>
                $this->H(
                    $roomRange
                ),

            '{{VALVES}}' =>
                $this->H(
                    $valves
                ),

            '{{DEMAND_AVERAGE}}' =>
                $this->H(
                    $this->FormatPercent(
                        $d['demandAverage']
                    )
                ),

            '{{DEMAND_MAXIMUM}}' =>
                $this->H(
                    $this->FormatPercent(
                        $d['demandMaximum']
                    )
                ),

            '{{DHW_TOP}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['dhwTop']
                    )
                ),

            '{{DHW_BOTTOM}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['dhwBottom']
                    )
                ),

            '{{BUFFER_TOP}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['bufferTop']
                    )
                ),

            '{{BUFFER_MIDDLE}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['bufferMiddle']
                    )
                ),

            '{{BUFFER_BOTTOM}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['bufferBottom']
                    )
                ),

            '{{METEO_MAX}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['meteoMax']
                    )
                ),

            '{{METEO_MEAN}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['meteoMean']
                    )
                ),

            '{{METEO_MIN}}' =>
                $this->H(
                    $this->FormatTemperature(
                        $d['meteoMin']
                    )
                ),

            '{{METEO_SOLAR}}' =>
                $this->H(
                    $this->FormatSolar(
                        $d['meteoSolar']
                    )
                ),

            '{{SOLCAST_ENERGY}}' =>
                $this->H(
                    $this->FormatEnergy(
                        $d['solcastEnergy']
                    )
                ),

            '{{SOLCAST_PEAK}}' =>
                $this->H(
                    $this->FormatPower(
                        $d['solcastPeak']
                    )
                ),

            '{{SOLCAST_CONFIDENCE}}' =>
                $this->H(
                    $this->FormatPercent(
                        $d['solcastConfidence']
                    )
                ),

            '{{SOLCAST_PEAK_TIME}}' =>
                $this->H(
                    $this->FormatTime(
                        $d['solcastPeakTime']
                    )
                ),

            '{{SOLCAST_PEAK_WINDOW}}' =>
                $this->H(
                    $peakWindow
                ),

            '{{UPDATED}}' =>
                date(
                    'H:i',
                    $d['timestamp']
                )
        ];

        return strtr(
            $template,
            $replace
        );
    }

    /*
     * ============================================================
     * FORMATIERUNG
     * ============================================================
     */

    private function FormatTemperature(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                1,
                '.',
                ''
            )
            .
            ' °C';
    }

    private function FormatPower(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                2,
                '.',
                ''
            )
            .
            ' kW';
    }

    private function FormatPercent(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                0,
                '.',
                ''
            )
            .
            ' %';
    }

    private function FormatCOP(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                2,
                '.',
                ''
            );
    }

    private function FormatSolar(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                2,
                '.',
                ''
            )
            .
            ' kWh/m²';
    }

    private function FormatEnergy(
        ?float $value
    ): string {
        if ($value === null) {
            return '—';
        }

        return
            number_format(
                $value,
                1,
                '.',
                ''
            )
            .
            ' kWh';
    }

    private function FormatTime(
        int $timestamp
    ): string {
        if ($timestamp <= 0) {
            return '—';
        }

        return
            date(
                'H:i',
                $timestamp
            );
    }

    private function H(
        string $value
    ): string {
        return
            htmlspecialchars(
                $value,
                ENT_QUOTES
                |
                ENT_SUBSTITUTE,
                'UTF-8'
            );
    }
}
