<?php

declare(strict_types=1);

class SulzbannWPRegelungVisu extends IPSModule
{
    private const OZW_ROOT_ID = 28320;
    private const SOLCAST_PARENT_ID = 16397;

    private const WP_HEATING_ID = 44357;
    private const WP_COOLING_ID = 17689;
    private const OUTSIDE_TEMP_ID = 50237;
    private const WP_FLOW_TEMP_ID = 27923;
    private const WP_RETURN_TEMP_ID = 45419;
    private const WP_POWER_ID = 50450;
    private const WP_HEAT_OUTPUT_ID = 32403;
    private const WP_COP_ID = 37700;
    private const WP_MODULATION_ID = 20837;

    private const BUFFER_TOP_ID = 27553;
    private const BUFFER_MIDDLE_ID = 52270;
    private const BUFFER_BOTTOM_ID = 18594;

    private const DHW_TOP_ID = 39112;
    private const DHW_BOTTOM_ID = 40096;

    private const METEO_MAX_ID = 42622;
    private const METEO_MEAN_ID = 11157;
    private const METEO_MIN_ID = 28499;
    private const METEO_SOLAR_ID = 36980;

    private const ROOM_TEMP_IDS = [
        47619, 43486, 20056, 59980,
        52271, 51361, 43453, 16944, 26539, 12860,
        32416, 25829, 45373, 29653
    ];

    private const VALVE_STATE_IDS = [
        18344, 50892, 37309, 57721,
        52749, 46861, 49967, 15602, 27850, 10375,
        33924, 19131, 58629, 12822
    ];

    private const VALVE_DEMAND_IDS = [
        39391, 22281, 49588, 51260,
        26389, 43578, 36459, 26819, 39276, 16937,
        19263, 17140, 14321, 20806
    ];

    public function Create(): void
    {
        parent::Create();

        /*
         * Symcon 9.0:
         * HTML-SDK in normaler Kachel und Grossansicht.
         */
        $this->SetVisualizationType(2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(2);

        foreach ($this->GetSourceVariableIDs() as $variableID) {
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }
    }

    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';

        if (!file_exists($htmlFile)) {
            return '<div>module.html nicht gefunden</div>';
        }

        $html = file_get_contents($htmlFile);

        if ($html === false) {
            return '<div>module.html konnte nicht gelesen werden</div>';
        }

        $initialData = $this->BuildPayload();

        $json = json_encode(
            $initialData,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

        if ($json === false) {
            $json = '{}';
        }

        return str_replace(
            '__SBWRV_INITIAL_DATA__',
            $json,
            $html
        );
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === 'Refresh') {
            $this->SendLiveValues();
            return;
        }

        throw new Exception(
            'Ungültige Aktion: ' . $Ident
        );
    }

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

        if ($Message === VM_UPDATE) {
            $this->SendLiveValues();
        }
    }

    private function SendLiveValues(): void
    {
        $payload = $this->BuildPayload();

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return;
        }

        $this->UpdateVisualizationValue($json);
    }

    private function BuildPayload(): array
    {
        /*
         * ============================================================
         * WP MASTER
         * ============================================================
         */

        $heating = $this->ReadBool(self::WP_HEATING_ID);
        $cooling = $this->ReadBool(self::WP_COOLING_ID);

        if ($heating && !$cooling) {
            $wpMode = 'Heizen';
            $wpModeCode = 'heating';

        } elseif ($cooling && !$heating) {
            $wpMode = 'Kühlen';
            $wpModeCode = 'cooling';

        } elseif (!$heating && !$cooling) {
            $wpMode = 'Standby';
            $wpModeCode = 'standby';

        } else {
            $wpMode = 'Unplausibel';
            $wpModeCode = 'fault';
        }

        /*
         * ============================================================
         * RAUMTEMPERATUREN
         * ============================================================
         */

        $roomTemperatures = [];

        foreach (self::ROOM_TEMP_IDS as $variableID) {
            $value = $this->ReadFloatNullable($variableID);

            if (
                $value !== null
                &&
                $value > -30.0
                &&
                $value < 60.0
            ) {
                $roomTemperatures[] = $value;
            }
        }

        $roomAverage = null;
        $roomMinimum = null;
        $roomMaximum = null;

        if (count($roomTemperatures) > 0) {
            $roomAverage =
                array_sum($roomTemperatures)
                /
                count($roomTemperatures);

            $roomMinimum = min($roomTemperatures);
            $roomMaximum = max($roomTemperatures);
        }

        /*
         * ============================================================
         * FBH VENTILE
         * ============================================================
         */

        $openValves = 0;

        foreach (self::VALVE_STATE_IDS as $variableID) {
            if ($this->ReadBool($variableID)) {
                $openValves++;
            }
        }

        /*
         * ============================================================
         * FBH STELLWERTE
         * ============================================================
         */

        $demands = [];

        foreach (self::VALVE_DEMAND_IDS as $variableID) {
            $value = $this->ReadFloatNullable($variableID);

            if ($value !== null) {
                $demands[] = $value;
            }
        }

        $demandAverage = null;
        $demandMaximum = null;

        if (count($demands) > 0) {
            $demandAverage =
                array_sum($demands)
                /
                count($demands);

            $demandMaximum = max($demands);
        }

        return [
            'type' => 'wp-regelung',
            'timestamp' => time(),

            /*
             * Ziel für Einstellungen.
             */
            'settingsObjectID' =>
                $this->FindSettingsObject(),

            'wp' => [
                'mode' => $wpMode,
                'modeCode' => $wpModeCode,

                'heating' => $heating,
                'cooling' => $cooling,

                'outside' =>
                    $this->ReadFloatNullable(
                        self::OUTSIDE_TEMP_ID
                    ),

                'flow' =>
                    $this->ReadFloatNullable(
                        self::WP_FLOW_TEMP_ID
                    ),

                'return' =>
                    $this->ReadFloatNullable(
                        self::WP_RETURN_TEMP_ID
                    ),

                'power' =>
                    $this->ReadFloatNullable(
                        self::WP_POWER_ID
                    ),

                'heatOutput' =>
                    $this->ReadFloatNullable(
                        self::WP_HEAT_OUTPUT_ID
                    ),

                'cop' =>
                    $this->ReadFloatNullable(
                        self::WP_COP_ID
                    ),

                'modulation' =>
                    $this->ReadFloatNullable(
                        self::WP_MODULATION_ID
                    )
            ],

            'rooms' => [
                'count' =>
                    count($roomTemperatures),

                'average' =>
                    $roomAverage,

                'minimum' =>
                    $roomMinimum,

                'maximum' =>
                    $roomMaximum
            ],

            'fbh' => [
                'open' =>
                    $openValves,

                'total' =>
                    count(
                        self::VALVE_STATE_IDS
                    ),

                'demandAverage' =>
                    $demandAverage,

                'demandMaximum' =>
                    $demandMaximum
            ],

            'buffer' => [
                'top' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_TOP_ID
                    ),

                'middle' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_MIDDLE_ID
                    ),

                'bottom' =>
                    $this->ReadFloatNullable(
                        self::BUFFER_BOTTOM_ID
                    )
            ],

            'dhw' => [
                'top' =>
                    $this->ReadFloatNullable(
                        self::DHW_TOP_ID
                    ),

                'bottom' =>
                    $this->ReadFloatNullable(
                        self::DHW_BOTTOM_ID
                    )
            ],

            'meteo' => [
                'maximum' =>
                    $this->ReadFloatNullable(
                        self::METEO_MAX_ID
                    ),

                'mean' =>
                    $this->ReadFloatNullable(
                        self::METEO_MEAN_ID
                    ),

                'minimum' =>
                    $this->ReadFloatNullable(
                        self::METEO_MIN_ID
                    ),

                'solar' =>
                    $this->ReadFloatNullable(
                        self::METEO_SOLAR_ID
                    )
            ],

            'solcast' => [
                'energyP50' =>
                    $this->ReadSolcastFloat(
                        'SolcastEnergy024P50'
                    ),

                'peak' =>
                    $this->ReadSolcastFloat(
                        'SolcastPeak024'
                    ),

                'confidence' =>
                    $this->ReadSolcastFloat(
                        'SolcastConfidence024'
                    ),

                'peakTime' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakTime024'
                    ),

                'peakWindowStart' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakWindowStart024'
                    ),

                'peakWindowEnd' =>
                    $this->ReadSolcastInteger(
                        'SolcastPeakWindowEnd024'
                    )
            ]
        ];
    }

    /*
     * ================================================================
     * SETTINGS-ZIEL SUCHEN
     * ================================================================
     */

    private function FindSettingsObject(): int
    {
        /*
         * 1. Bevorzugt unter Siemens OWZ.
         */

        $result =
            $this->FindParameterBelowWpRegulation(
                self::OZW_ROOT_ID
            );

        if ($result > 0) {
            return $result;
        }

        /*
         * 2. Globaler Fallback.
         */

        $result =
            $this->FindParameterBelowWpRegulation(
                0
            );

        if ($result > 0) {
            return $result;
        }

        /*
         * 3. Falls Parameter nicht gefunden:
         * wenigstens WP Regelung öffnen.
         */

        $wpRegulation =
            $this->FindRecursiveByName(
                0,
                'WP Regelung'
            );

        if ($wpRegulation > 0) {
            return $wpRegulation;
        }

        return 0;
    }

    private function FindParameterBelowWpRegulation(
        int $rootID
    ): int {
        if (
            $rootID !== 0
            &&
            !IPS_ObjectExists($rootID)
        ) {
            return 0;
        }

        $queue = [$rootID];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            foreach (
                IPS_GetChildrenIDs($current)
                as $childID
            ) {
                $name =
                    IPS_GetName($childID);

                if (
                    $name === 'Parameter'
                    &&
                    $this->HasAncestorNamed(
                        $childID,
                        'WP Regelung'
                    )
                ) {
                    return (int) $childID;
                }

                $queue[] = (int) $childID;
            }
        }

        return 0;
    }

    private function HasAncestorNamed(
        int $objectID,
        string $name
    ): bool {
        $current = $objectID;
        $guard = 0;

        while (
            $current > 0
            &&
            $guard < 50
        ) {
            $current =
                IPS_GetParent($current);

            if ($current <= 0) {
                return false;
            }

            if (
                IPS_GetName($current)
                ===
                $name
            ) {
                return true;
            }

            $guard++;
        }

        return false;
    }

    private function FindRecursiveByName(
        int $parentID,
        string $name
    ): int {
        $queue = [$parentID];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            foreach (
                IPS_GetChildrenIDs($current)
                as $childID
            ) {
                if (
                    IPS_GetName($childID)
                    ===
                    $name
                ) {
                    return (int) $childID;
                }

                $queue[] = (int) $childID;
            }
        }

        return 0;
    }

    /*
     * ================================================================
     * QUELLVARIABLEN
     * ================================================================
     */

    private function GetSourceVariableIDs(): array
    {
        $ids = [
            self::WP_HEATING_ID,
            self::WP_COOLING_ID,

            self::OUTSIDE_TEMP_ID,
            self::WP_FLOW_TEMP_ID,
            self::WP_RETURN_TEMP_ID,

            self::WP_POWER_ID,
            self::WP_HEAT_OUTPUT_ID,
            self::WP_COP_ID,
            self::WP_MODULATION_ID,

            self::BUFFER_TOP_ID,
            self::BUFFER_MIDDLE_ID,
            self::BUFFER_BOTTOM_ID,

            self::DHW_TOP_ID,
            self::DHW_BOTTOM_ID,

            self::METEO_MAX_ID,
            self::METEO_MEAN_ID,
            self::METEO_MIN_ID,
            self::METEO_SOLAR_ID
        ];

        foreach (self::ROOM_TEMP_IDS as $id) {
            $ids[] = $id;
        }

        foreach (self::VALVE_STATE_IDS as $id) {
            $ids[] = $id;
        }

        foreach (self::VALVE_DEMAND_IDS as $id) {
            $ids[] = $id;
        }

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
                $ids[] = $id;
            }
        }

        return array_values(
            array_unique($ids)
        );
    }

    /*
     * ================================================================
     * SOLCAST
     * ================================================================
     */

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

        return
            $this->ReadFloatNullable($id);
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

        return
            (int) GetValue($id);
    }

    /*
     * ================================================================
     * SICHERE LESER
     * ================================================================
     */

    private function ReadBool(
        int $variableID
    ): bool {
        if (
            $variableID <= 0
            ||
            !IPS_VariableExists(
                $variableID
            )
        ) {
            return false;
        }

        return
            (bool) GetValue($variableID);
    }

    private function ReadFloatNullable(
        int $variableID
    ): ?float {
        if (
            $variableID <= 0
            ||
            !IPS_VariableExists(
                $variableID
            )
        ) {
            return null;
        }

        $value =
            GetValue($variableID);

        if (!is_numeric($value)) {
            return null;
        }

        return
            (float) $value;
    }
}
