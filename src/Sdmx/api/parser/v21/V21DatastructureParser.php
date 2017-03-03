<?php

namespace Sdmx\api\parser\v21;


use Sdmx\api\entities\Codelist;
use Sdmx\api\entities\DataflowStructure;
use Sdmx\api\entities\Dimension;
use Sdmx\api\parser\CodelistParser;
use Sdmx\api\parser\DataStructureParser;
use SimpleXMLElement;

class V21DataStructureParser implements DataStructureParser
{

    /**
     * @var CodelistParser $codelistParser
     */
    private $codelistParser;

    /**
     * V21DataStructureParser constructor.
     * @param CodelistParser $codelistParser
     */
    public function __construct(CodelistParser $codelistParser)
    {
        $this->codelistParser = $codelistParser;
    }


    /**
     * @param string $data
     * @return DataflowStructure[]
     */
    public function parse($data)
    {
        $xml = new SimpleXMLElement($data);

        $codelists = $this->parseCodelists($xml);

        return $this->parseDataStructures($xml, $codelists);
    }

    /**
     * @param SimpleXMLElement $xml
     * @return string][]
     */
    private function parseCodelists(SimpleXMLElement $xml)
    {
        $codelists = $xml->xpath('//mes:Structure/mes:Structures/str:Codelists/str:Codelist');
        $result = [];
        foreach ($codelists as $codelist) {
            $codelistName = $this->getCodelistName($codelist);
            $result[$codelistName] = $this->codelistParser->parse($codelist);
        }

        return $result;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param array $codelists
     * @return DataflowStructure[]
     */
    private function parseDataStructures(SimpleXMLElement $xml, array $codelists)
    {
        $result = [];

        $structures = $xml->xpath('//mes:Structure/mes:Structures/str:DataStructures/str:DataStructure');
        foreach ($structures as $structure) {
            $result[] = $this->parseDataStructure($structure, $codelists);
        }

        return $result;
    }

    /**
     * @param SimpleXMLElement $structure
     * @param array $codelists
     * @return DataflowStructure
     */
    private function parseDataStructure(SimpleXMLElement $structure, array $codelists)
    {
        $flowStructure = new DataflowStructure();

        $this->fillDsMainData($structure, $flowStructure);
        $this->fillDsDimensionData($structure, $flowStructure, $codelists);

        return $flowStructure;
    }

    /**
     * @param SimpleXMLElement $structure
     * @param DataflowStructure $flowStructure
     */
    private function fillDsMainData(SimpleXMLElement $structure, DataflowStructure $flowStructure)
    {
        $flowStructure->setAgency((string)$structure['agencyID']);
        $flowStructure->setId((string)$structure['id']);
        $flowStructure->setVersion((string)$structure['version']);
        $name = $structure->xpath('.//com:Name[@xml:lang="en"]');
        if (count($name) > 0) {
            $flowStructure->setName((string)$name[0]);
        }
    }

    /**
     * @param SimpleXMLElement $structure
     * @param DataflowStructure $flowStructure
     * @param array $codelists
     */
    private function fillDsDimensionData(SimpleXMLElement $structure, DataflowStructure $flowStructure, array $codelists)
    {
        $dimensions = $structure->xpath('.//str:DataStructureComponents/str:DimensionList/str:Dimension');
        $position = 0;
        foreach ($dimensions as $dimension) {
            $parsedDimension = $this->parseDimension($dimension, ++$position);
            $codelist = $parsedDimension->getCodelist();
            $codelistIdentifier = $codelist->getFullIdentifier();

            if (array_key_exists($codelistIdentifier, $codelists)) {
                $codelist->setCodes($codelists[$codelistIdentifier]);
            }

            $flowStructure->setDimension($parsedDimension);
        }
        $flowStructure->setTimeDimension($this->parseTimeDimension($structure));
    }

    /**
     * @param SimpleXMLElement $dimension
     * @param int $position
     * @return Dimension
     */
    private function parseDimension(SimpleXMLElement $dimension, $position)
    {
        $result = new Dimension();

        $result->setId((string)$dimension['id']);
        $result->setCodelist($this->parseDimensionCodeList($dimension));
        $result->setName($this->getDimensionName($dimension));
        $result->setPosition($position);

        return $result;
    }

    /**
     * @param SimpleXMLElement $dimension
     * @return Codelist
     */
    private function parseDimensionCodeList(SimpleXMLElement $dimension)
    {
        $localRepresentation = $dimension->xpath('.//str:LocalRepresentation/str:Enumeration/Ref');

        if (count($localRepresentation) == 0) {
            return null;
        }

        $localRepresentation = $localRepresentation[0];
        $codelist = new Codelist();
        $codelist->setId((string)$localRepresentation['id']);
        $codelist->setAgency((string)$localRepresentation['agencyID']);
        $codelist->setVersion((string)$localRepresentation['version']);

        return $codelist;
    }

    /**
     * @param SimpleXMLElement $dimension
     * @return string
     */
    private function getDimensionName(SimpleXMLElement $dimension)
    {
        $concept = $dimension->xpath('.//str:ConceptIdentity/Ref');

        if (count($concept) == 0) {
            return '';
        }
        $concept = $concept[0];

        return (string)$concept['id'];
    }

    /**
     * @param SimpleXMLElement $structure
     * @return string
     */
    private function parseTimeDimension(SimpleXMLElement $structure)
    {
        $timeDimension = $structure->xpath('.//str:DataStructureComponents/str:DimensionList/str:TimeDimension');

        if (count($timeDimension) == 0) {
            return '';
        }

        $timeDimension = $timeDimension[0];

        return (string)$timeDimension['id'];
    }

    /**
     * @param $codelist
     * @return string
     */
    private function getCodelistName($codelist)
    {
        $agency = (string)$codelist['agencyID'];
        $id = (string)$codelist['id'];
        $version = (string)$codelist['version'];
        $codelistName = $agency . '/' . $id . '/' . $version;
        return $codelistName;
    }
}