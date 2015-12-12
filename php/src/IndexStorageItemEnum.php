<?php

namespace PhpIntegrator;

/**
 * Defines item types that are present inside the index.
 */
class IndexStorageItemEnum
{
    const SETTINGS                              = 'settings';
    const FILES                                 = 'files';
    const STRUCTURAL_ELEMENT_TYPES              = 'structural_element_types';
    const ACCESS_MODIFIERS                      = 'access_modifiers';
    const STRUCTURAL_ELEMENTS                   = 'structural_elements';
    const STRUCTURAL_ELEMENTS_PARENTS_LINKED    = 'structural_elements_parents_linked';
    const STRUCTURAL_ELEMENTS_INTERFACES_LINKED = 'structural_elements_interfaces_linked';
    const STRUCTURAL_ELEMENTS_TRAITS_LINKED     = 'structural_elements_traits_linked';
    const FUNCTIONS                             = 'functions';
    const FUNCTIONS_PARAMETERS                  = 'functions_parameters';
    const FUNCTIONS_THROWS                      = 'functions_throws';
    const PROPERTIES                            = 'properties';
    const CONSTANTS                             = 'constants';
}
