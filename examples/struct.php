<?php


use Oktaax\Console;
use Oktaax\Utils\Struct;
use Oktaax\Utils\Types\ArrayOf;

use function Oktaax\Utils\{_o, getter};

require __DIR__ . "/../vendor/autoload.php";


#[Attribute(Attribute::TARGET_PROPERTY)]
class Getter
{
    public function __construct() {}
}

class Address
{
    public string $street;
    public string $city;
    public string $province;
    public string $country;

    #[Getter]
    public string $fullAddress;

    public function move(
        string $street,
        string $city,
        string $province,
        string $country
    ): void {}
}

class Project
{
    public string $name;
    public string $description;
    public bool $private;
    public int $stars;

    /** @var string[] */
    public array $contributors;

    public function star(int $count = 1): void {}
    public function addContributor(string $name): void {}
}

class Person
{
    public string $firstName;
    public string $lastName;

    #[Getter]
    public string $fullName;

    #[Getter]
    public int $age;

    #[Getter]
    public string $location;

    public DateTime $birthDay;

    public Address $address;

    /** @var string[] */
    public array $skills;

    /** @var Project[] */
    public array $projects;

    #[Getter]

    public int $projectCount;

    #[Getter]

    public int $totalStars;

    public function rename(
        string $first,
        string $last
    ): void {}

    public function moveTo(
        string $city,
        string $country
    ): void {}

    public function addSkill(
        string $skill
    ): void {}

    public function addProject(
        string $name,
        string $description
    ): Project {

        return new Project;
    }

    public function introduce(): string
    {
        return "";
    }
}

/** @var Struct<Person> */
$person = _o(

    firstName: "Jefy",
    lastName: "Okta",

    birthDay: new DateTime("2002-10-06"),

    address: _o(
        street: "Jl. HR. Soebrantas",
        city: "Pekanbaru",
        province: "Riau",
        country: "Indonesia",

        fullAddress: getter(function () {
            return "{$this->street}, {$this->city}, {$this->province}, {$this->country}";
        }),

        move: function (
            string $street,
            string $city,
            string $province,
            string $country
        ) {
            $this->street = $street;
            $this->city = $city;
            $this->province = $province;
            $this->country = $country;
        }
    ),

    skills: [
        "PHP",
        "TypeScript",
        "Electron",
    ],

    projects: [],

    fullName: getter(function () {
        return "{$this->firstName} {$this->lastName}";
    }),

    age: getter(function () {
        return $this->birthDay->diff(new DateTime())->y;
    }),

    location: getter(function () {
        return "{$this->address->city}, {$this->address->country}";
    }),

    projectCount: getter(function () {
        return count($this->projects);
    }),

    totalStars: getter(function () {
        return array_sum(
            array_map(
                fn($project) => $project->stars,
                $this->projects
            )
        );
    }),



    rename: function (
        string $first,
        string $last
    ) {
        $this->firstName = $first;
        $this->lastName = $last;
    },

    moveTo: function (
        string $city,
        string $country
    ) {
        $this->address->city = $city;
        $this->address->country = $country;
    },

    addSkill: function (string $skill) {
        $skills = $this->skills;
        $skills[] = $skill;
        $this->skills = $skills;
    },

    addProject: function (
        string $name,
        string $description
    ) {
        $projects = $this->projects;

        $projects[] = _o(
            name: $name,
            description: $description,
            private: false,
            stars: 0,
            contributors: [
                $this->fullName,
            ],

            star: function (int $count = 1) {
                $this->stars += $count;
            },

            addContributor: function (string $name) {
                $contributors = $this->contributors;
                $contributors[] = $name;
                $this->contributors = $contributors;
            }
        );

        $this->projects = $projects;

        return end($projects);
    },

    introduce: function () {

        return <<<TEXT
👋 {$this->fullName}

Age      : {$this->age}
Location : {$this->location}
Projects : {$this->projectCount}
Stars    : {$this->totalStars}
TEXT;
    }
);

Console::log($person->introduce());

$person->rename("John", "Doe");

$person->moveTo(
    "Jakarta",
    "Indonesia"
);

$person->addSkill("Swift");

$project = $person->addProject(
    name: "HighTex Desktop",
    description: "Academic document processor"
);

$project->star(500);
$project->addContributor("Okta");


$user = Struct::strict(
    [
        "name" => 'string',

        "age" => 'int',

        "birthday" => DateTime::class,

        "email" => [
            'string',
            'null'
        ],

        "friend" => [
            Person::class,
            'null',
        ],

        "data" => 'mixed',
        'skills' => ArrayOf::type('string')
    ],

    name: "Jefy",
    age: 23,
    birthday: new DateTime("2002-10-06"),
    email: null,
    friend: null,
    data: [1, 2, 3],
    skills: []
);
$user->friend = null;

$user->skills = ['', ''];

// $user->friend[] = new Person;
Console::log($user);


$ovj = new stdClass;
