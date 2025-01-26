<?php

namespace App\Models\Resource;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;

class BookOrder extends Model
{
    
    protected $fillable = [
        'book_list_id',
        'student_id',
        'order_date',
        'return_date',
        'status'
    ];

    public function book()
    {
        return $this->belongsTo(BookList::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

}
