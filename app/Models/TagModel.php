<?php

namespace App\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table = 'tags';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['nama', 'slug'];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural_no_zero',
        'nama' => 'required|max_length[50]',
        'slug' => 'required|max_length[50]|is_unique[tags.slug,id,{id}]',
    ];
}
