<?php

namespace PhpIntegrator;

/**
 * Defines item types that are present inside the index.
 */
class IndexStorageItemEnum
{
    const SETTINGS                      = 'settings';
    const FILES                         = 'files';
    const STRUCTURE_TYPES               = 'structure_types';
    const ACCESS_MODIFIERS              = 'access_modifiers';
    const FILES_NAMESPACES              = 'files_namespaces';
    const FILES_NAMESPACES_IMPORTS      = 'files_namespaces_imports';
    const STRUCTURES                    = 'structures';
    const STRUCTURES_PARENTS_LINKED     = 'structures_parents_linked';
    const STRUCTURES_INTERFACES_LINKED  = 'structures_interfaces_linked';
    const STRUCTURES_TRAITS_LINKED      = 'structures_traits_linked';
    const STRUCTURES_TRAITS_ALIASES     = 'structures_traits_aliases';
    const STRUCTURES_TRAITS_PRECEDENCES = 'structures_traits_precedences';
    const FUNCTIONS                     = 'functions';
    const FUNCTIONS_PARAMETERS          = 'functions_parameters';
    const FUNCTIONS_THROWS              = 'functions_throws';
    const PROPERTIES                    = 'properties';
    const CONSTANTS                     = 'constants';
}
