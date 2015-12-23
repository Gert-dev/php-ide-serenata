<?php

namespace MJS\TopSort\Implementations;

/**
 *
 * Implements grouped topological-sort based on fixed arrays (\SplFixedArray).
 *
 * @author Marc J. Schmidt <marc@marcjschmidt.de>
 */
class GroupedFixedArraySort extends GroupedArraySort
{
    /**
     * @param object  $element
     * @param integer $minLevel
     */
    protected function injectElement($element, $minLevel)
    {
        if ($group = $this->getFirstGroup($element->type, $minLevel)) {
            $this->addItemAt($group->position + $group->length, $element);
            $group->length++;

            //increase all following groups +1
            $i = $group->position;
            foreach ($this->groups as $tempGroup) {
                if ($tempGroup->position > $i) {
                    $tempGroup->position++;
                }
            }

            $element->addedAtLevel = $group->level;
        } else {
            $this->groups[] = (object)[
                'type' => $element->type,
                'level' => $this->groupLevel,
                'position' => $this->position,
                'length' => 1
            ];
            $element->addedAtLevel = $this->groupLevel;
            $this->sorted[$this->position] = $element->id;
            $this->position++;

            $this->groupLevel++;
        }
    }

    /**
     * @param integer $position
     * @param object  $element
     */
    public function addItemAt($position, $element)
    {
        //shift all items >>
        for ($i = $this->position; $i > $position; $i--) {
            $this->sorted[$i] = $this->sorted[$i - 1];
        }

        $this->sorted[$position] = $element->id;
        $this->position++;
    }

    /**
     * {@inheritDoc}
     */
    public function sort()
    {
        return $this->doSort()->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @return \SplFixedArray
     */
    public function doSort()
    {
        $this->position = 0;
        $this->sorted = new \SplFixedArray(count($this->elements));

        foreach ($this->elements as $element) {
            $parents = [];
            $this->visit($element, $parents);
        }

        return $this->sorted;
    }
}