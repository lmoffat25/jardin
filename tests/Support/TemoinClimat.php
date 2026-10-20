<?php

namespace Tests\Support;

/**
 * Un référentiel climatique témoin (V135, V136) : douze communes et quatre
 * stations, toutes inventées (RG-28), écrites dans un dossier temporaire au
 * format de `bibliotheque/climat/` (D-86). La station 99000003 a une période
 * incomplète, et sa température absurde la trahirait si elle était retenue.
 *
 * Les valeurs attendues se calculent à la main : référence 8,0 °C en
 * février-avril, 6 j/°C, 0,65 °C par 100 m.
 */
final class TemoinClimat
{
    public const PARAMETRES = <<<'YAML'
        station_reference: "99000001"
        mois: [2, 3, 4]
        jours_par_degre: 6
        gradient_c_par_100_m: 0.65
        montagne:
          profil_id: 5
          au_dessus_de_m: 600
        demander_altitude:
          de_m: 400
          a_m: 800
        YAML;

    public const STATIONS = <<<'CSV'
        num;nom;latitude;longitude;altitude_m;periode_complete;t01;t02;t03;t04;t05;t06;t07;t08;t09;t10;t11;t12
        99000001;Station de la Plaine-Témoin;48.0;2.0;100;1;3.0;5.0;8.0;11.0;15.0;18.0;20.0;20.0;17.0;12.0;7.0;4.0
        99000002;Station du Sud-Témoin;43.0;3.0;50;1;8.0;10.0;11.0;12.0;17.0;21.0;24.0;24.0;20.0;16.0;11.0;9.0
        99000003;Station Incomplète-Témoin;45.0;4.0;200;0;20.0;20.0;20.0;20.0;20.0;20.0;20.0;20.0;20.0;20.0;20.0;20.0
        99000004;Station des Hauteurs-Témoin;45.0;6.0;1000;1;0.0;2.0;3.0;4.0;8.0;12.0;15.0;15.0;11.0;7.0;3.0;1.0
        CSV;

    public const COMMUNES = <<<'CSV'
        code_insee;nom;codes_postaux;latitude;longitude;altitude_m
        99101;Val-de-Plaine-Témoin;99100;48.0;2.0;100
        99102;Val-du-Sud-Témoin;99200;43.0;3.0;50
        99103;Val-Incomplet-Témoin;99300;45.0;4.0;200
        99104;Val-des-Hauteurs-Témoin;99400;45.0;6.0;720
        99105;Val-des-Coteaux-Témoin;99510 99520;48.0;2.1;460
        99106;Val-des-Cimes-Témoin;99600;45.0;6.1;910
        99107;Val-Doux-Témoin;99700;43.1;3.0;327
        99108;Val-Premier-Témoin;99801;48.1;2.0;110
        99109;Val-Second-Témoin;99802;48.2;2.0;120
        99110;Val-Troisième-Témoin;99803;48.3;2.0;130
        99111;Val-Quatrième-Témoin;99804;48.4;2.0;140
        99112;Écluse-Saint-Témoin;99520;48.0;2.2;90
        CSV;

    /** Écrit le témoin dans un dossier neuf ; rend son chemin. */
    public static function dossier(?string $stations = null, ?string $communes = null): string
    {
        $dossier = sys_get_temp_dir().'/temoin-climat-'.bin2hex(random_bytes(6));
        mkdir($dossier);
        file_put_contents($dossier.'/parametres.yaml', self::PARAMETRES);
        file_put_contents($dossier.'/stations.csv', ($stations ?? self::STATIONS)."\n");
        file_put_contents($dossier.'/communes.csv', ($communes ?? self::COMMUNES)."\n");

        return $dossier;
    }
}
