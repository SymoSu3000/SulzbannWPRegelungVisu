<?php

declare(strict_types=1);


/*
 * =============================================================================
 * SULZBANN WP REGELUNG VISU
 * HTML-SDK
 * VERSION 1.2
 * =============================================================================
 *
 * Änderungen V1.2:
 *
 * - Compact-/Detail-Ansicht wie bei der bewährten WP-Visu
 * - beim normalen Öffnen der Kachel wird automatisch Detail angezeigt
 * - kein zweiter Klick auf das Vollbild-Symbol nötig
 * - Einstellungen-Ziel wird rekursiv gesucht
 * - Einstellungen-ID wird zusammen mit den Live-Daten übertragen
 * - Livewerte weiterhin über UpdateVisualizationValue()
 * - keine HTMLBox-Rewrites
 * - keine Schreibzugriffe auf WP / KNX / OZW
 *
 * =============================================================================
 */


class SulzbannWPRegelungVisu extends IPSModule
{

    /*
     * =========================================================================
     * SYSTEM
     * =========================================================================
     */

    private const OZW_ROOT_ID = 28320;

    private const SOLCAST_PARENT_ID = 16397;


    /*
     * =========================================================================
     * WP
     * =========================================================================
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
     * =========================================================================
     * SPEICHER
     * =========================================================================
     */

    private const BUFFER_TOP_ID = 27553;

    private const BUFFER_MIDDLE_ID = 52270;

    private const BUFFER_BOTTOM_ID = 18594;

    private const DHW_TOP_ID = 39112;

    private const DHW_BOTTOM_ID = 40096;


    /*
     * =========================================================================
     * METEOSCHWEIZ MORGEN
     * =========================================================================
     */

    private const METEO_MAX_ID = 42622;

    private const METEO_MEAN_ID = 11157;

    private const METEO_MIN_ID = 28499;

    private const METEO_SOLAR_ID = 36980;


    /*
     * =========================================================================
     * RAUMTEMPERATUREN
     * =========================================================================
     */

    private const ROOM_TEMP_IDS = [

        /*
         * EG
         */
        47619,
        43486,
        20056,
        59980,

        /*
         * OG
         */
        52271,
        51361,
        43453,
        16944,
        26539,
        12860,

        /*
         * Einliegerwohnung
         */
        32416,
        25829,
        45373,
        29653

    ];


    /*
     * =========================================================================
     * FBH VENTILZUSTÄNDE
     * =========================================================================
     */

    private const VALVE_STATE_IDS = [

        /*
         * EG
         */
        18344,
        50892,
        37309,
        57721,

        /*
         * OG
         */
        52749,
        46861,
        49967,
        15602,
        27850,
        10375,

        /*
         * Einliegerwohnung
         */
        33924,
        19131,
        58629,
        12822

    ];


    /*
     * =========================================================================
     * FBH STELLWERTE
     * =========================================================================
     */

    private const VALVE_DEMAND_IDS = [

        /*
         * EG
         */
        39391,
        22281,
        49588,
        51260,

        /*
         * OG
         */
        26389,
        43578,
        36459,
        26819,
        39276,
        16937,

        /*
         * Einliegerwohnung
         */
        19263,
        17140,
        14321,
        20806

    ];


    /*
     * =========================================================================
     * CREATE
     * =========================================================================
     */

    public function Create(): void
    {

        parent::Create();


        /*
         * Echte HTML-SDK-Kachel.
         */
        $this->SetVisualizationType(
            1
        );
    }


    /*
     * =========================================================================
     * APPLY CHANGES
     * =========================================================================
     */

    public function ApplyChanges(): void
    {

        parent::ApplyChanges();


        $this->SetVisualizationType(
            1
        );


        /*
         * Relevante Variablen beobachten.
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


    /*
     * =========================================================================
     * HTML
     * =========================================================================
     */

    public function GetVisualizationTile(): string
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
                '<div>module.html fehlt.</div>';
        }


        $html =
            file_get_contents(
                $file
            );


        if (
            $html === false
        ) {

            return
                '<div>module.html konnte nicht geladen werden.</div>';
        }


        return
            $html;
    }


    /*
     * =========================================================================
     * LIVE UPDATE
     * =========================================================================
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
            $Message !== VM_UPDATE
        ) {

            return;
        }


        $this->SendLiveValues();
    }


    /*
     * =========================================================================
     * REQUEST ACTION
     * =========================================================================
     */

    public function RequestAction(
        $Ident,
        $Value
    ): void {

        switch (
            $Ident
        ) {

            case 'Refresh':

                $this->SendLiveValues();

                return;


            default:

                throw new Exception(
                    'Invalid Ident'
                );
        }
    }


    /*
     * =========================================================================
     * LIVE DATEN SENDEN
     * =========================================================================
     */

    private function SendLiveValues(): void
    {

        $payload =
            $this->BuildVisualizationData();


        $json =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            );


        if (
            $json === false
        ) {

            return;
        }


        $this->UpdateVisualizationValue(
            $json
        );
    }


    /*
     * =========================================================================
     * DATEN AUFBAUEN
     * =========================================================================
     */

    private function BuildVisualizationData(): array
    {

        /*
         * ---------------------------------------------------------------------
         * WP MASTER
         * ---------------------------------------------------------------------
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
         * ---------------------------------------------------------------------
         * RAUMTEMPERATUREN
         * ---------------------------------------------------------------------
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
         * ---------------------------------------------------------------------
         * FBH VENTILE
         * ---------------------------------------------------------------------
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
         * ---------------------------------------------------------------------
         * FBH STELLWERTE
         * ---------------------------------------------------------------------
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
         * ---------------------------------------------------------------------
         * SOLCAST
         * ---------------------------------------------------------------------
         */

        $solcastP50 =
            $this->ReadSolcastFloat(
                'SolcastEnergy024P50'
            );


        $solcastPeak =
            $this->ReadSolcastFloat(
                'SolcastPeak024'
            );


        $solcastConfidence =
            $this->ReadSolcastFloat(
                'SolcastConfidence024'
            );


        $solcastPeakTime =
            $this->ReadSolcastInteger(
                'SolcastPeakTime024'
            );


        $solcastPeakWindowStart =
            $this->ReadSolcastInteger(
                'SolcastPeakWindowStart024'
            );


        $solcastPeakWindowEnd =
            $this->ReadSolcastInteger(
                'SolcastPeakWindowEnd024'
            );


        /*
         * ---------------------------------------------------------------------
         * EINSTELLUNGEN
         * ---------------------------------------------------------------------
         */

        $settingsObjectID =
            $this->FindSettingsObject();


        /*
         * ---------------------------------------------------------------------
         * RÜCKGABE
         * ---------------------------------------------------------------------
         */

        return [

            'timestamp' =>
                time(),

            'settingsObjectID' =>
                $settingsObjectID,

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
                    $solcastP50,

                'peak' =>
                    $solcastPeak,

                'confidence' =>
                    $solcastConfidence,

                'peakTime' =>
                    $solcastPeakTime,

                'peakWindowStart' =>
                    $solcastPeakWindowStart,

                'peakWindowEnd' =>
                    $solcastPeakWindowEnd

            ]

        ];
    }


    /*
     * =========================================================================
     * QUELLVARIABLEN
     * =========================================================================
     */

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


        /*
         * Solcast Variablen per Ident.
         */

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


    /*
     * =========================================================================
     * SOLCAST
     * =========================================================================
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


        if (
            $id <= 0
        ) {

            return null;
        }


        return
            $this->ReadFloatNullable(
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


        if (
            $id <= 0
        ) {

            return 0;
        }


        return
            (int) GetValue(
                $id
            );
    }


    /*
     * =========================================================================
     * EINSTELLUNGEN SUCHEN
     * =========================================================================
     *
     * Gesuchte Struktur:
     *
     * Siemens OWZ
     * └── WP Regelung
     *     └── Parameter
     *
     * Im Gegensatz zur alten Version wird jetzt rekursiv gesucht.
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


        /*
         * Zuerst WP Regelung irgendwo unter Siemens OWZ suchen.
         */
        $wpRegulation =
            $this->FindRecursiveByName(
                self::OZW_ROOT_ID,
                'WP Regelung'
            );


        if (
            $wpRegulation <= 0
        ) {

            /*
             * Falls Struktur später anders benannt wird:
             * wenigstens Siemens OWZ öffnen.
             */
            return
                self::OZW_ROOT_ID;
        }


        /*
         * Parameter innerhalb WP Regelung suchen.
         */
        $parameters =
            $this->FindRecursiveByName(
                $wpRegulation,
                'Parameter'
            );


        if (
            $parameters > 0
        ) {

            return
                $parameters;
        }


        /*
         * WP Regelung selbst als Fallback.
         */
        return
            $wpRegulation;
    }


    /*
     * =========================================================================
     * REKURSIVE OBJEKTSUCHE
     * =========================================================================
     */

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


        $queue = [
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


                $object =
                    IPS_GetObject(
                        $childID
                    );


                /*
                 * Nur Objekte mit potentiellen Kindern weitersuchen.
                 */
                if (
                    isset(
                        $object['ObjectType']
                    )
                    &&
                    in_array(
                        (int) $object['ObjectType'],
                        [
                            0,
                            1,
                            2,
                            3,
                            5,
                            6
                        ],
                        true
                    )
                ) {

                    $queue[] =
                        (int) $childID;
                }
            }
        }


        return 0;
    }


    /*
     * =========================================================================
     * SICHERE LESEFUNKTIONEN
     * =========================================================================
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


        if (
            !is_numeric(
                $value
            )
        ) {

            return null;
        }


        return
            (float) $value;
    }

}
