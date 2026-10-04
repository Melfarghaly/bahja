<?php

namespace App\Enums;

/**
 * What a family allows the nursery to do with photos of their child.
 */
enum ConsentScope: string
{
    case Wall = 'wall';                     // photos of the child, seen by the family
    case GroupPhotos = 'group_photos';      // shown in group photos other families see
}
