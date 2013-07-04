<?php

function marc_parser($record, &$output) {

    $output['control_number'] = $record->text('marc:controlfield[@tag="001"]');

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
                $output['main_author'] = array(
                    'name' => $node->text('marc:subfield[@code="a"]')
                );
                $output['main_author']['authority'] = $node->text('marc:subfield[@code="0"]');
                break;
            case 245:
                $output['title'] = $node->text('marc:subfield[@code="a"]');
                $output['subtitle'] = $node->text('marc:subfield[@code="b"]');
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
                $output['added_author'] = $node->text('marc:subfield[@code="a"]');
                break;
            case 856:
                $desc = $node->text('marc:subfield[@code="3"]');
                if ($desc === 'Cover image') {
                    $output['cover'] = $node->text('marc:subfield[@code="u"]');
                }
                break;

        }
    }
}
