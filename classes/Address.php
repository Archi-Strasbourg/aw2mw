<?php

namespace AW2MW;

class Address
{
    public static function getFullAddressName(array $address)
    {
        $a = new \archiAdresse();
        $idEvenementGroupeAdresse = $a->getIdEvenementGroupeAdresseFromIdAdresse($address['idAdresse']);

        $reqAdresseDuGroupeAdresse = "
            SELECT ha1.idAdresse as idAdresse,ha1.numero as numero,
            ha1.idRue as idRue, IF(ha1.idIndicatif='0','',i.nom)
            as nomIndicatif, ha1.idQuartier as idQuartier, ha1.idSousQuartier as idSousQuartier
            FROM historiqueAdresse ha2, historiqueAdresse ha1
            LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ha1.idAdresse
            LEFT JOIN indicatif i ON i.idIndicatif = ha1.idIndicatif
            WHERE ha2.idAdresse = ha1.idAdresse
            AND ae.idEvenement ='".mysql_real_escape_string($idEvenementGroupeAdresse)."'

            GROUP BY ha1.idAdresse, ha1.idHistoriqueAdresse
            HAVING ha1.idHistoriqueAdresse = max(ha2.idHistoriqueAdresse)
            ORDER BY ha1.numero,ha1.idRue
        ";

        $resAdresseDuGroupeAdresse = $a->connexionBdd->requete($reqAdresseDuGroupeAdresse);

        if (mysql_num_rows($resAdresseDuGroupeAdresse) > 1) {
            $txtAdresses = '';
            $arrayNumero = [];
            while ($fetchAdressesGroupeAdresse = mysql_fetch_assoc($resAdresseDuGroupeAdresse)) {
                $isAdresseCourante = false;
                if ($address['idAdresse'] == $fetchAdressesGroupeAdresse['idAdresse']) {
                    $isAdresseCourante = true;
                }

                if ($fetchAdressesGroupeAdresse['idRue'] == '0' || $fetchAdressesGroupeAdresse['idRue'] == '') {
                    if ($fetchAdressesGroupeAdresse['idQuartier'] != ''
                        && $fetchAdressesGroupeAdresse['idQuartier'] != '0'
                    ) {
                        $arrayNumero[$a->getIntituleAdresseFrom(
                            $fetchAdressesGroupeAdresse['idAdresse'],
                            'idAdresse',
                            ['noSousQuartier' => true, 'noQuartier' => false, 'noVille' => true]
                        )][] =
                            [
                                'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                                'numero'            => $fetchAdressesGroupeAdresse['numero'],
                                'isAdresseCourante' => $isAdresseCourante,
                            ];
                    }

                    if ($fetchAdressesGroupeAdresse['idSousQuartier'] != ''
                        && $fetchAdressesGroupeAdresse['idSousQuartier'] != '0'
                    ) {
                        $arrayNumero[$a->getIntituleAdresseFrom(
                            $fetchAdressesGroupeAdresse['idAdresse'],
                            'idAdresse',
                            ['noSousQuartier' => false, 'noQuartier' => true, 'noVille' => true]
                        )][] =
                            [
                                'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                                'numero'            => $fetchAdressesGroupeAdresse['numero'],
                                'isAdresseCourante' => $isAdresseCourante,
                            ];
                    }
                } else {
                    $arrayNumero[$a->getIntituleAdresseFrom(
                        $fetchAdressesGroupeAdresse['idRue'],
                        'idRueWithNoNumeroAuthorized',
                        ['noSousQuartier' => true, 'noQuartier' => true, 'noVille' => true]
                    )][] =
                        [
                            'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                            'numero'            => $fetchAdressesGroupeAdresse['numero'],
                            'isAdresseCourante' => $isAdresseCourante,
                        ];
                }
            }

            // affichage adresses regroupees
            foreach ($arrayNumero as $intituleRue => $arrayInfosNumero) {
                foreach ($arrayInfosNumero as $indice => $infosNumero) {
                    if ($infosNumero['numero'] == '' || $infosNumero['numero'] == '0') {
                        //rien
                    } else {
                        $txtAdresses .= $infosNumero['numero'].$infosNumero['indicatif'].'-';
                    }
                }

                $txtAdresses = trim($txtAdresses, '-');

                $txtAdresses .= $intituleRue.', ';
            }
            $txtAdresses = trim($txtAdresses, ', ');

            return $txtAdresses;
        }
    }
}
