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
        47619,
        43486,
        20056,
        59980,

        52271,
        51361,
        43453,
        16944,
        26539,
        12860,

        32416,
        25829,
        45373,
        29653
    ];

    private const VALVE_STATE_IDS = [
        18344,
        50892,
        37309,
        57721,

        52749,
        46861,
        49967,
        15602,
        27850,
        10375,

        33924,
        19131,
        58629,
        12822
    ];

    private const VALVE_DEMAND_IDS = [
        39391,
        22281,
        49588,
        51260,

        26389,
        43578,
        36459,
        26819,
        39276,
        16937,

        19263,
        17140,
        14321,
        20806
    ];


    public function Create()
    {
        parent::Create();

        /*
         * IP-Symcon 9.0
         *
         * Typ 2:
         * HTML-SDK in normaler Kachel UND Vollbild.
         */
        $this->SetVisualizationType(2);
    }


    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(2);

        /*
         * Relevante Messwerte abonnieren.
         */
        foreach (
            $this->GetSourceVariableIDs()
            as $variableID
        ) {

            if (
                $variableID > 0
                &&
                IPS_VariableExists(
                    $variableID
                )
            ) {

                $this->RegisterMessage(
                    $variableID,
                    VM_UPDATE
                );
            }
        }
    }


    public function GetVisualizationTile()
    {
        $file =
            __DIR__
            .
            '/module.html';


        if (
            !file_exists(
                $file
            )
        ) {

            return
                '<div style="padding:60px 12px;color:var(--content-color)">'
                .
                'module.html fehlt.'
                .
                '</div>';
        }


        $html =
            file_get_contents(
                $file
            );


        if (
            $html === false
        ) {

            return
                '<div style="padding:60px 12px;color:var(--content-color)">'
                .
                'module.html konnte nicht geladen werden.'
                .
                '</div>';
        }


        /*
         * Ganz wichtig:
         *
         * Die Kachel bekommt sofort Initialdaten.
         *
         * Dadurch sind:
         *
         * - Werte
         * - Betriebsart
         * - Einstellungen-Ziel
         *
         * schon beim ersten Render vorhanden.
         *
         * Der Settings-Button muss nicht erst auf
         * einen späteren Refresh warten.
         */

        $initialData =
            $this->BuildVisualizationData();


        $initialJson =
            json_encode(
                $initialData,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_HEX_TAG
                |
                JSON_HEX_AMP
                |
                JSON_HEX_APOS
                |
                JSON_HEX_QUOT
            );


        if (
            $initialJson === false
        ) {

            $initialJson =
                '{}';
        }


        return
            str_replace(
                '__SBWRV_INITIAL_DATA__',
                $initialJson,
                $html
            );
    }


    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ) {

        parent::MessageSink(
            $TimeStamp,
            $SenderID,
            $Message,
            $Data
        );


        if (
            $Message
            ===
            VM_UPDATE
        ) {

            $this->SendLiveValues();
        }
    }


    public function RequestAction(
        $Ident,
        $Value
    ) {

        switch (
            $Ident
        ) {

            case 'Refresh':

                $this->SendLiveValues();

                return;


            default:

                throw new Exception(
                    'Invalid Ident: '
                    .
                    $Ident
                );
        }
    }


    private function SendLiveValues(): void
    {
        $json =
            json_encode(
                $this->BuildVisualizationData(),
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            );


        if (
            $json
            !==
            false
        ) {

            $this->UpdateVisualizationValue(
                $json
            );
        }
    }


    private function BuildVisualizationData(): array
    {
        /*
         * ==============================================================
         * WP MASTER-BETRIEBSART
         * ==============================================================
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

            $wpMode =
                'Heizen';

            $wpModeCode =
                'heating';

        } elseif (
            $cooling
            &&
            !$heating
        ) {

            $wpMode =
                'Kühlen';

            $wpModeCode =
                'cooling';

        } elseif (
            !$heating
            &&
            !$cooling
        ) {

            $wpMode =
                'Standby';

            $wpModeCode =
                'standby';

        } else {

            $wpMode =
                'Unplausibel';

            $wpModeCode =
                'fault';
        }


        /*
         * ==============================================================
         * RAUMTEMPERATUREN
         * ==============================================================
         */

        $roomTemperatures =
            [];


        foreach (
            self::ROOM_TEMP_IDS
            as $variableID
        ) {

            $value =
                $this->ReadFloatNullable(
                    $variableID
                );


            if (
                $value !== null
                &&
                $value > -30.0
                &&
                $value < 60.0
            ) {

                $roomTemperatures[] =
                    $value;
            }
        }


        $roomAverage =
            null;

        $roomMinimum =
            null;

        $roomMaximum =
            null;


        if (
            count(
                $roomTemperatures
            ) > 0
        ) {

            $roomAverage =
                array_sum(
                    $roomTemperatures
                )
                /
                count(
                    $roomTemperatures
                );


            $roomMinimum =
                min(
                    $roomTemperatures
                );


            $roomMaximum =
                max(
                    $roomTemperatures
                );
        }


        /*
         * ==============================================================
         * FBH VENTILE
         * ==============================================================
         */

        $openValves =
            0;


        foreach (
            self::VALVE_STATE_IDS
            as $variableID
        ) {

            if (
                $this->ReadBool(
                    $variableID
                )
            ) {

                $openValves++;
            }
        }


        /*
         * ==============================================================
         * FBH STELLWERTE
         * ==============================================================
         */

        $demands =
            [];


        foreach (
            self::VALVE_DEMAND_IDS
            as $variableID
        ) {

            $value =
                $this->ReadFloatNullable(
                    $variableID
                );


            if (
                $value !== null
            ) {

                $demands[] =
                    $value;
            }
        }


        $demandAverage =
            null;

        $demandMaximum =
            null;


        if (
            count(
                $demands
            ) > 0
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


        /*
         * ==============================================================
         * RÜCKGABE
         * ==============================================================
         */

        return [

            'timestamp' =>
                time(),


            /*
             * Settings-Ziel wird bereits mit Initialdaten gesendet.
             */
            'settingsObjectID' =>
                $this->FindSettingsObject(),


            'wp' => [

                'mode' =>
                    $wpMode,

                'modeCode' =>
                    $wpModeCode,

                'heating' =>
                    $heating,

                'cooling' =>
                    $cooling,

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
                    count(
                        $roomTemperatures
                    ),

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


    private function GetSourceVariableIDs(): array
    {
        $ids = [

            self::WP_HEATING_ID,
            self::WP_COOLING_ID,

            self::OUTSIDE_TEMP_ID,

            self::WP_FLOW_TEMP_ID,
            self::WP_RETURN_TEMP_ID,

            self::BUFFER_TOP_ID,
            self::BUFFER_MIDDLE_ID,
            self::BUFFER_BOTTOM_ID,

            self::DHW_TOP_ID,
            self::DHW_BOTTOM_ID,

            self::WP_POWER_ID,
            self::WP_HEAT_OUTPUT_ID,
            self::WP_COP_ID,
            self::WP_MODULATION_ID,

            self::METEO_MAX_ID,
            self::METEO_MEAN_ID,
            self::METEO_MIN_ID,
            self::METEO_SOLAR_ID
        ];


        foreach (
            self::ROOM_TEMP_IDS
            as $id
        ) {

            $ids[] =
                $id;
        }


        foreach (
            self::VALVE_STATE_IDS
            as $id
        ) {

            $ids[] =
                $id;
        }


        foreach (
            self::VALVE_DEMAND_IDS
            as $id
        ) {

            $ids[] =
                $id;
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


            if (
                $id > 0
            ) {

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


        return
            (int) $id;
    }


    private function ReadSolcastFloat(
        string $ident
    ): ?float {

        $id =
            $this->GetSolcastVariableID(
                $ident
            );


        return
            (
                $id > 0
            )
                ?
                $this->ReadFloatNullable(
                    $id
                )
                :
                null;
    }


    private function ReadSolcastInteger(
        string $ident
    ): int {

        $id =
            $this->GetSolcastVariableID(
                $ident
            );


        return
            (
                $id > 0
            )
                ?
                (int) GetValue(
                    $id
                )
                :
                0;
    }


    /*
     * ==============================================================
     * SETTINGS-ZIEL
     * ==============================================================
     *
     * Gesucht wird:
     *
     * Siemens OWZ
     *   > WP Regelung
     *       > Parameter
     */

    private function FindSettingsObject(): int
    {
        if (
            !IPS_ObjectExists(
                self::OZW_ROOT_ID
            )
        ) {

            return 0;
        }


        $wpRegulation =
            $this->FindRecursiveByName(
                self::OZW_ROOT_ID,
                'WP Regelung'
            );


        if (
            $wpRegulation <= 0
        ) {

            return 0;
        }


        $parameters =
            $this->FindRecursiveByName(
                $wpRegulation,
                'Parameter'
            );


        return
            (
                $parameters > 0
            )
                ?
                $parameters
                :
                $wpRegulation;
    }


    private function FindRecursiveByName(
        int $parentID,
        string $name
    ): int {

        if (
            $parentID <= 0
            ||
            !IPS_ObjectExists(
                $parentID
            )
        ) {

            return 0;
        }


        $queue =
            [
                $parentID
            ];


        while (
            count(
                $queue
            ) > 0
        ) {

            $current =
                array_shift(
                    $queue
                );


            foreach (
                IPS_GetChildrenIDs(
                    $current
                )
                as $childID
            ) {

                if (
                    IPS_GetName(
                        $childID
                    )
                    ===
                    $name
                ) {

                    return
                        (int) $childID;
                }


                $queue[] =
                    (int) $childID;
            }
        }


        return 0;
    }


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
            (bool) GetValue(
                $variableID
            );
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
            GetValue(
                $variableID
            );


        return
            is_numeric(
                $value
            )
                ?
                (float) $value
                :
                null;
    }
}
