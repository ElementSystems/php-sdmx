<?php

namespace Sdmx\api\parser\v21;


use Sdmx\api\entities\Dataflow;
use Sdmx\api\entities\DsdIdentifier;
use Sdmx\api\parser\DataflowParser;
use Sdmx\api\parser\ParserUtils;
use SimpleXMLElement;

class V21DataflowParser implements DataflowParser
{
    const ID = 'id';
    const AGENCY_ID = 'agencyID';
    const VERSION = 'version';

    /**
     * @param string $data
     * @return Dataflow[]
     */
    public function parse($data)
    {
        $result = array();
        $dataWoNs = ParserUtils::removeNamespaces($data);
        $xml = new SimpleXMLElement($dataWoNs);

        $flows = $xml->xpath('/Structure/Structures/Dataflows/Dataflow');
        foreach ($flows as $flow) {
            $dataflow = new Dataflow();
            $dataflow->setId((string) $flow[self::ID]);
            $dataflow->setAgency((string) $flow[self::AGENCY_ID]);
            $dataflow->setVersion((string)$flow[self::VERSION]);

            $name = $flow->xpath('./Name[@lang="en"]');
            if(count($name) > 0){
                $dataflow->setName((string)$name[0]);
            }


            $ref = $flow->xpath('./Structure/Ref');
            if(count($ref) > 0){
                $ref = $ref[0];
                $dsd = new DsdIdentifier(
                    (string) $ref[self::ID],
                    (string) $ref[self::AGENCY_ID],
                    (string) $ref[self::VERSION]
                );
                $dataflow->setDsdIdentifier($dsd);
            }

            $result[] = $dataflow;
        }

        return $result;
    }
}