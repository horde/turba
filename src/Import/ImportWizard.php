<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/apache ASL
 * @package Turba
 */

namespace Horde\Turba\Import;

use Horde_Data;
use Horde_Data_Base;

/**
 * Skip CSV/TSV delimiter, column-mapping, and ISO date wizard steps when
 * the uploaded file is already Turba-shaped.
 */
class ImportWizard
{
    /**
     * Advance past no-op wizard pages in the same request.
     *
     * @param Horde_Variables $vars Form variables (header, sep, dataKeys, …).
     * @param array<string, mixed> $param Horde_Data nextStep() params (time_fields may be updated).
     * @param array<string, mixed> $sourceMap Destination address-book map.
     * @param array<string, array> $attributes Turba attribute definitions.
     * @param mixed $nextStep Current Horde_Data step or parsed rows.
     *
     * @return mixed
     */
    public function autoAdvance(
        Horde_Data_Base $data,
        $vars,
        array &$param,
        array $sourceMap,
        array $attributes,
        $nextStep
    ) {
        $fileData = $data->storage->get('file_data');

        if ($nextStep === Horde_Data::IMPORT_CSV && is_string($fileData) && $fileData !== '') {
            $headers = ColumnMapper::parseHeaderRow($fileData, ',', '"');
            if (ColumnMapper::looksLikeNativeHeader($headers, ColumnMapper::knownExportHeaders($sourceMap))) {
                $vars->header = 1;
                $vars->sep = ',';
                $vars->quote = '"';
                $vars->fields = count($headers);
                $nextStep = $data->nextStep(Horde_Data::IMPORT_CSV, $param);
            }
        }

        if ($nextStep === Horde_Data::IMPORT_TSV && is_string($fileData) && $fileData !== '') {
            $headers = ColumnMapper::parseHeaderRow($fileData, "\t", '"');
            if (ColumnMapper::looksLikeNativeHeader($headers, ColumnMapper::knownExportHeaders($sourceMap))) {
                $vars->header = 1;
                $nextStep = $data->nextStep(Horde_Data::IMPORT_TSV, $param);
            }
        }

        if ($nextStep === Horde_Data::IMPORT_MAPPED) {
            $rows = $data->storage->get('data') ?: [];
            $headers = array_keys($rows[0] ?? []);
            $destKeys = ColumnMapper::destFieldKeys($sourceMap);
            if (ColumnMapper::canSkipMapping($headers, $destKeys, $sourceMap)) {
                [$dataKeys, $appKeys] = ColumnMapper::identityPairs($headers, $destKeys);
                $vars->dataKeys = implode("\t", $dataKeys);
                $vars->appKeys = implode("\t", $appKeys);
                $param['time_fields'] = ColumnMapper::keepNonIsoTimeFields(
                    ColumnMapper::timeFields($sourceMap, $attributes),
                    $rows
                );
                $nextStep = $data->nextStep(Horde_Data::IMPORT_MAPPED, $param);
            }
        }

        if ($nextStep === Horde_Data::IMPORT_DATETIME) {
            $dates = $data->storage->get('dates') ?: [];
            if (ColumnMapper::allDateSamplesAreIso($dates)) {
                $delimiter = [];
                $format = [];
                foreach (array_keys($dates) as $key) {
                    $delimiter[$key] = '-';
                    $format[$key] = 'year/month/mday';
                }
                $vars->delimiter = $delimiter;
                $vars->format = $format;
                $nextStep = $data->nextStep(Horde_Data::IMPORT_DATETIME, $param);
            }
        }

        return $nextStep;
    }
}
