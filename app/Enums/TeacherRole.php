<?php

namespace App\Enums;

enum TeacherRole: string
{
    case HeadTeacher = 'head_teacher';
    case Teacher = 'teacher';
    case Assistant = 'assistant';
}
