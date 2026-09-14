<?php

declare(strict_types=1);


/*
 * =============================================================================
 * SULZBANN WP REGELUNG VISU
 * HTML-SDK
 * VERSION 1.0
 * =============================================================================
 *
 * Ziel:
 *
 * - echte HTML-SDK-Kachel
 * - keine ~HTMLBox
 * - keine periodische komplette HTML-Neuladung
 * - Live-Aktualisierung über UpdateVisualizationValue()
 * - HTML empfängt Werte über handleMessage()
 * - Theme übernimmt die Symcon-Darstellung
 * - Einstellungen-Button springt zu:
 *
 *       Siemens OWZ
 *       > WP Regelung
 *       > Parameter
 *
 * - KEINE Schreibzugriffe auf WP, OZW oder KNX
 *
 * =============================================================================
 */


class SulzbannWPRegelungVisu extends IPSModule
{

    /*
     * =========================================================================
     * FESTE SULZBANN-IDS
     * =========================================================================
     */


    /*
     * Siemens OWZ Root
     */
    private const OZW_ROOT_ID = 28320;


    /*
     * Wärmepumpe Betriebsart
     */
    private const WP_HEATING_ID = 44357;

    private const WP_COOLING_ID = 17689;


    /*
     * Außentemperatur
     */
    private const OUTSIDE_TEMP_ID = 50237;


    /*
     * WP Temperaturen
     */
    private const WP_FLOW_TEMP_ID = 27923;

    private const WP_RETURN_TEMP_ID = 45419;


    /*
     * Puffer
     */
    private const BUFFER_TOP_ID = 27553;

    private const BUFFER_MIDDLE_ID = 52270;

    private const BUFFER_BOTTOM_ID = 18594;


    /*
     * Boiler
     */
    private const DHW_TOP_ID = 39112;

    private const DHW_BOTTOM_ID = 40096;


    /*
     * Leistungswerte WP
     */
    private const WP_POWER_ID = 50450;

    private const WP_HEAT_OUTPUT_ID = 32403;

    private const WP_COP_ID = 37700;

    private const WP_MODULATION_ID = 20837;


    /*
     * MeteoSchweiz Morgen
     */
    private const METEO_MAX_ID = 42622;

    private const METEO_MEAN_ID = 11157;

    private const METEO_MIN_ID = 28499;

    private const METEO_SOLAR_ID = 36980;


    /*
     * Solcast Parent
     */
    private const SOLCAST_PARENT_ID = 16397;


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

    public function Create()
    {

        parent::Create();


        /*
         * Echtes HTML-SDK aktivieren.
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

    public function ApplyChanges()
    {

        parent::ApplyChanges();


        /*
         * HTML-SDK sicher aktiv.
         */
        $this->SetVisualizationType(
            1
        );


        /*
         * Vorhandene Nachrichtenregistrierungen entfernen.
         */
        foreach (
            $this->GetMessageList()
            as $senderID => $messages
        ) {

            foreach (
                $messages
                as $message
            ) {

                $this->UnregisterMessage(
                    (int) $senderID,
                    (int) $message
                );
            }
        }


        /*
         * Alle benötigten Variablen abonnieren.
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
     * MESSAGE SINK
     * =========================================================================
     *
     * Wird eine abonnierte Variable verändert, bekommt die offene
     * HTML-SDK-Kachel nur neue Daten.
     *
     * Es wird NICHT die ganze Seite neu aufgebaut.
     */

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
            $Message !== VM_UPDATE
        ) {

            return;
        }


        $payload =
            $this->BuildVisualizationData();


        $this->UpdateVisualizationValue(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            )
        );
    }


    /*
     * =========================================================================
     * HTML-SDK
     * =========================================================================
     */

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


        /*
         * Initialwerte.
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


        /*
         * Ziel für Einstellungen-Button.
         */
        $settingsObjectID =
            $this->FindSettingsObject();


        $html =
            str_replace(
                '__SBWRV_INITIAL_DATA__',
                $initialJson,
                $html
            );


        $html =
            str_replace(
                '__SBWRV_SETTINGS_OBJECT_ID__',
                (string) $settingsObjectID,
                $html
            );


        return
            $html;
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
         * RÜCKGABE
         * ---------------------------------------------------------------------
         */

        return [

            'timestamp' =>
                time(),

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
     * ALLE QUELLVARIABLEN
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
         * Solcast dynamisch per Ident.
         */

        $solcastIdents = [

            'SolcastEnergy024P50',
            'SolcastPeak024',
            'SolcastConfidence024',
            'SolcastPeakTime024',
            'SolcastPeakWindowStart024',
            'SolcastPeakWindowEnd024'

        ];


        foreach (
            $solcastIdents
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
        ) {

            return 0;
        }


        if (
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
     * EINSTELLUNGEN FINDEN
     * =========================================================================
     *
     * Erwartete Struktur:
     *
     * Siemens OWZ
     * └── WP Regelung
     *     └── Parameter
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
            $this->FindDirectChildByName(
                self::OZW_ROOT_ID,
                'WP Regelung'
            );


        if (
            $wpRegulation <= 0
        ) {

            return
                self::OZW_ROOT_ID;
        }


        $parameters =
            $this->FindDirectChildByName(
                $wpRegulation,
                'Parameter'
            );


        if (
            $parameters > 0
        ) {

            return
                $parameters;
        }


        return
            $wpRegulation;
    }


    private function FindDirectChildByName(
        int $parentID,
        string $name
    ): int {

        if (
            !IPS_ObjectExists(
                $parentID
            )
        ) {

            return 0;
        }


        foreach (
            IPS_GetChildrenIDs(
                $parentID
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
