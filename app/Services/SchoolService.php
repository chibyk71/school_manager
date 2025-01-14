<?php 

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use Illuminate\Support\Facades\Hash;

class SchoolService
{
    public function createSchool(array $data): School
    {
        // Create the school record
        $school = School::create([
            'name' => $data['name'],
            'domain' => $data['domain'],
        ]);
    
        // Check if the user already exists
        $admin = User::where('email', $data['email'])->first();
    
        if ($admin) {
            // User exists: Assign the admin role to the user for this school
            $admin->assignRole('admin', $school->id);
    
            // Notify the user about the admin assignment
            $admin->notify(new MadeAdminOfSchoolNotification($school));
    
            // Return school along with a message
            throw new \Exception("User with email {$data['email']} already exists and has been assigned as the admin of this school.", 409);
        } else {
            // User doesn't exist: Create a new admin user
            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['admin_password']),
            ]);
    
            // Assign admin role and associate with the school
            $admin->assignRole('admin', $school->id);
        }
    
        return $school;
    }

    public function assignAdmin(array $user, School $school) {
        $admin = User::createOrFirst(['email'=> $user['email']], ['first_name'=>$user]);
    }
}
