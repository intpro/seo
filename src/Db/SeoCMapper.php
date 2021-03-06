<?php

namespace Interpro\Seo\Db;

use Illuminate\Support\Facades\DB;
use Interpro\Core\Contracts\Ref\ARef;
use Interpro\Core\Contracts\Taxonomy\Types\AType;
use Interpro\Core\Helpers;
use Interpro\Core\Taxonomy\Enum\TypeRank;
use Interpro\Extractor\Contracts\Db\CMapper;
use Interpro\Extractor\Contracts\Selection\SelectionUnit;
use Interpro\Seo\Collections\MapSeoCollection;
use Interpro\Seo\Creation\SeoItemFactory;
use Interpro\Extractor\Contracts\Selection\Tuner;

class SeoCMapper implements CMapper
{
    private $factory;
    private $units = [];
    private $tuner;

    public function __construct(SeoItemFactory $factory, Tuner $tuner)
    {
        $this->factory = $factory;
        $this->tuner = $tuner;
    }

    /**
     * @return string
     */
    public function getFamily()
    {
        return 'seo';
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->units = [];
    }

    private function addResultToCollection(AType $ownerType, MapSeoCollection $collection, array $result)
    {
        foreach($result as $item_array)
        {
            $field_name = $item_array['name'];

            if($ownerType->fieldExist($field_name))
            {
                $fieldType = $ownerType->getFieldType($field_name);

                $item = $this->factory->create($fieldType, $item_array['value']);

                $ref = new \Interpro\Core\Ref\ARef($ownerType, $item_array['entity_id']);

                $collection->addItem($ref, $field_name, $item);
            }
        }
    }

    /**
     * @param \Interpro\Core\Contracts\Ref\ARef $ref
     * @param bool $asUnitMember
     *
     * @return \Interpro\Extractor\Contracts\Collections\MapCCollection
     */
    public function getByRef(ARef $ref, $asUnitMember = false)
    {
        $ownerType = $ref->getType();
        $owner_name = $ownerType->getName();
        $rank = $ownerType->getRank();

        if($rank === TypeRank::GROUP and $asUnitMember)
        {
            $selectionUnit = $this->tuner->getSelection($owner_name, 'group');

            return $this->select($selectionUnit);
        }

        $owner_id = $ref->getId();

        $key = $owner_name.'_'.$owner_id;

        if(array_key_exists($key, $this->units))
        {
            return $this->units[$key];
        }

        $collection = new MapSeoCollection($this->factory);
        $this->units[$key] = $collection;

        $query = DB::table('seos');
        $query->where('seos.entity_name', '=', $owner_name);
        $query->where('seos.entity_id', '=', $owner_id);

        $result = Helpers::laravel_db_result_to_array($query->get(['entity_name', 'entity_id', 'name', 'value']));

        $this->addResultToCollection($ownerType, $collection, $result);

        return $collection;
    }

    /**
     * @param \Interpro\Extractor\Contracts\Selection\SelectionUnit $selectionUnit
     *
     * @return \Interpro\Extractor\Contracts\Collections\MapCCollection
     */
    public function select(SelectionUnit $selectionUnit)
    {
        $ownerType = $selectionUnit->getType();

        $unit_number = $selectionUnit->getNumber();
        $key = 'unit_'.$unit_number;

        if(array_key_exists($key, $this->units))
        {
            return $this->units[$key];
        }

        $collection = new MapSeoCollection($this->factory);
        $this->units[$key] = $collection;

        $query = DB::table('seos');
        $query->where('seos.entity_name', '=', $selectionUnit->getTypeName());

        if($selectionUnit->closeToIdSet())
        {
            $query->whereIn('seos.entity_id', $selectionUnit->getIdSet());
        }

        $result = Helpers::laravel_db_result_to_array($query->get(['entity_name', 'entity_id', 'name', 'value']));

        $this->addResultToCollection($ownerType, $collection, $result);


        return $collection;
    }
}
