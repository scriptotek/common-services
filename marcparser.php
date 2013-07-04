<?php

function marc_parser($record, &$output) {

    $output['control_number'] = $record->text('marc:controlfield[@tag="001"]');
    $output['authors'] = array();
    $output['electronic'] = false;

    foreach ($record->xpath('marc:datafield') as $node) {
        $marcfield = intval($node->attributes()->tag);
        switch ($marcfield) {
            case 8:
                $output['form'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 10:
                $output['lccn'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 20:
                if (!isset($output['isbn'])) $output['isbn'] = array();
                $isbn = explode(' ', $node->text('marc:subfield[@code="a"]'));
                array_push($output['isbn'], $isbn[0]);
                break;
            case 82:
                if (!isset($output['klass'])) $output['klass'] = array();
                $klass = $node->text('marc:subfield[@code="a"]');
                $klass = str_replace('/', '', $klass);
                foreach ($output['klass'] as $kitem) {
                    if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                        continue 3;
                    }
                }
                array_push($output['klass'], array('kode' => $klass, 'system' => 'dewey'));
                break;
            case 89:
                if (!isset($output['klass'])) $output['klass'] = array();
                $klass = $node->text('marc:subfield[@code="a"]');
                foreach ($output['klass'] as $kitem) {
                    if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                        continue 3;
                    }
                }
                array_push($output['klass'], array('kode' => $klass, 'system' => 'dewey'));
                break;

            case 100:
                $author = array(
                    'name' => $node->text('marc:subfield[@code="a"]'),
                    'authority' => $node->text('marc:subfield[@code="0"]'),
                    'role' => 'main'
                );
                $output['authors'][] = $author;
                break;
            case 245:
                $output['title'] = $node->text('marc:subfield[@code="a"]');
                $output['subtitle'] = $node->text('marc:subfield[@code="b"]');
                if (preg_match('/elektronisk ressurs/', $node->text('marc:subfield[@code="h"]'))) {
                    $output['electronic'] = true;
                }
                break;
            case 250:
                $output['edition'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 260:
                $output['publisher'] = $node->text('marc:subfield[@code="b"]');
                $output['year'] = preg_replace('/[^0-9,]|,[0-9]*$/', '', current($node->xpath('marc:subfield[@code="c"]')));
                break;
            case 300:
                $output['pages'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 491:
                $output['series'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 650:
                $emne = $node->text('marc:subfield[@code="a"]');
                  $tmp = array('emne' => trim($emne, '.'));

                  $system = $node->text('marc:subfield[@code="2"]');
                  if ($system !== false) $tmp['system'] = $system;

                  $subdiv = $node->text('marc:subfield[@code="x"]');
                  if ($subdiv !== false) $tmp['subdiv'] = trim($subdiv, '.');
                  
                  $time = $node->text('marc:subfield[@code="y"]');
                  if ($time !== false) $tmp['time'] = $time;

                  $geo = $node->text('marc:subfield[@code="z"]');
                  if ($geo !== false) $tmp['geo'] = $geo;

                  array_push($output['subjects'], $tmp);
                break;
            case 700:
                $author = array(
                    'name' => $node->text('marc:subfield[@code="a"]'),
                    'authority' => $node->text('marc:subfield[@code="0"]'),
                    'role' => 'added'
                );
                $output['authors'][] = $author;
                break;
            case 856:
            case 956:
                # MARC 21 uses field 856 for electronic "links", where you can have URLs for example covers images and/or blurbs.
                # 956 ?

                    // <marc:datafield tag="856" ind1="4" ind2="2">
                    //     <marc:subfield code="3">Beskrivelse fra forlaget (kort)</marc:subfield>
                    //     <marc:subfield code="u">http://content.bibsys.no/content/?type=descr_publ_brief&amp;isbn=0521176832</marc:subfield>
                    // </marc:datafield>
                    // <marc:datafield tag="956" ind1="4" ind2="2">
                    //     <marc:subfield code="3">Omslagsbilde</marc:subfield>
                    //     <marc:subfield code="u">http://innhold.bibsys.no/bilde/forside/?size=mini&amp;id=9780521176835.jpg</marc:subfield>
                    //     <marc:subfield code="q">image/jpeg</marc:subfield>
                    // </marc:datafield>
                $desc = $node->text('marc:subfield[@code="3"]');
                if (in_array($desc, array('Cover image', 'Omslagsbilde'))) {
                    $output['cover_image'] = $node->text('marc:subfield[@code="u"]');
                }
                if (in_array($desc, array('Beskrivelse fra forlaget (kort)', 'Beskrivelse fra forlaget (lang)'))) {
                    $output['description'] = $node->text('marc:subfield[@code="u"]');
                }
                break;

        }
    }
}
