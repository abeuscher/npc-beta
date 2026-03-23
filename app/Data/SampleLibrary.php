<?php

namespace App\Data;

class SampleLibrary
{
    public static function firstNames(): array
    {
        return [
            // Looney Tunes
            'Bugs', 'Daffy', 'Elmer', 'Porky', 'Tweety', 'Sylvester', 'Yosemite',
            'Foghorn', 'Pepé', 'Speedy', 'Wile', 'Marvin', 'Lola', 'Tasmanian',
            'Henery', 'Hippety',
            // Hanna-Barbera
            'Fred', 'Wilma', 'Barney', 'Betty', 'Bamm-Bamm', 'Pebbles', 'Yogi',
            'Huckleberry', 'Snagglepuss', 'Dastardly', 'Muttley', 'Scooby', 'Shaggy',
            'Daphne', 'Velma', 'George', 'Elroy', 'Benny', 'Penelope',
            // Rocky & Bullwinkle
            'Rocky', 'Bullwinkle', 'Boris', 'Natasha', 'Dudley', 'Nell', 'Snidely',
            'Peabody', 'Sherman',
            // Tiny Toons
            'Buster', 'Babs', 'Plucky', 'Hamton', 'Elmyra', 'Montana', 'Fifi',
            'Sweetie', 'Calamity',
            // Animaniacs
            'Yakko', 'Wakko', 'Pinky', 'Slappy', 'Skippy', 'Flavio', 'Marita',
            'Pesto', 'Squit',
            // Buckaroo Banzai
            'Buckaroo', 'Emilio', 'Penny', 'Peggy', 'Sidney', '"New Jersey"', 'Tohichi',
            'Reno', 'Billy', '"Perfect Tommy"', '"Rawhide"', 'Margaret', 'Casper',
            'Scooter', '"Pinky"', 'Eunice',
        ];
    }

    public static function lastNames(): array
    {
        return [
            // Looney Tunes
            'Bunny', 'Duck', 'Fudd', 'Pig', 'Leghorn', 'Le Pew', 'Gonzales',
            'Runner', 'Coyote', 'Martian', 'Devil', 'Hawk', 'Pussycat', 'Hopper', 'Antony',
            // Hanna-Barbera
            'Flintstone', 'Rubble', 'Jetson', 'Bear', 'Hound', 'McGraw', 'Doo', 'Rogers',
            // Rocky & Bullwinkle
            'Moose', 'Squirrel', 'Badenov', 'Fatale', 'Do-Right', 'Whiplash',
            // Tiny Toons
            'La Fume',
            // Animaniacs
            'Warner', 'Brain',
            // Dr. Seuss-y invented surnames
            'Grinch', 'Lorax', 'Zook', 'Wump', 'Zans', 'Nook', 'Sneetch', 'Truffula',
            'Borfin', 'Gack', 'Nazzim', 'Diffendoofer', 'Flummox', 'Zummzian',
            'Gluppity', 'Yuzz', 'Wumbus', 'Vipper', 'Jedd', 'Preep', 'Proo', 'Nink',
            'Yop', 'Vroom', 'Steg', 'Gox', 'Rink',
            // Buckaroo Banzai
            'Banzai', 'Lizardo', 'Whorfin', 'Priddy', 'Zweibel', 'Bigbooté', 'Emdall',
            'Hikita', 'Nevada', 'Travers', 'Widmark', 'McKinley', 'Catburd', 'Parker',
            "O'Connor", 'Gomez', 'Cunningham', 'Lindley', 'Carruthers', 'Johnson', 'Smirnoff',
        ];
    }

    public static function emailDomains(): array
    {
        return [
            'acme.com',
            'kc-company.net',
            'bedrock.gov',
            'spacely-sprockets.com',
            'cogswell-cogs.net',
            'jellystone.park',
            'frostbitefalls.mn',
            'wossamotta.edu',
            'toon-town.net',
            'megacorp.toon',
            'globochem.toon',
            'seussworks.com',
            'thneedville.org',
            'whoville.net',
            'nool-hills.gov',
        ];
    }

    public static function streetAddresses(): array
    {
        return [
            '1 Bedrock Place', '2 Slate Avenue', '3 Gravel Road', '4 Pebble Court',
            '10 Truffula Lane', '12 Nool Hills Road', '7 Thneed Avenue', '99 Lorax Way',
            '100 Skypad Apartments', '42 Orbit Circle', '8 Comet Drive', '15 Nebula Close',
            '3 Acme Loop', '17 Wile E. Way', '55 Looney Drive', '6 Anvil Street',
            '23 Jellystone Way', '44 Yogi Court', '88 Boo-Boo Lane', '5 Picnic Basket Road',
            '9 Wossamotta Blvd', '11 Moose Run', '33 Squirrel Hollow', '7 Boris Lane',
            '1 Animaniac Avenue', '2 Warner Lot Road', '13 Wakko Way', '41 Brain Street',
            '8 Snidely Crescent', '22 Dudley Drive', '6 Nell Court', '50 Mountie Road',
            '99 Seussian Circle', '1 Zummzian Way', '7 Gack Boulevard', '3 Diffendoofer Lane',
        ];
    }

    public static function cities(): array
    {
        return [
            'Bedrock', 'Orbit City', 'Frostbite Falls', 'Toontown', 'Acme Acres',
            'Jellystone Park', 'Whoville', 'Nool Hills', 'Thneedville', 'Wossamotta',
            'Capitol City', 'Shelbyville', 'Quahog', 'Arlen', 'Pawnee',
            'Stoolbend', 'Duckburg', 'Calisota', 'Transylvania', 'Coolsville',
        ];
    }

    public static function states(): array
    {
        return ['MN', 'CA', 'WY', 'OH', 'IL', 'TX', 'OR', 'AK'];
    }

    public static function eventTitles(): array
    {
        return [
            'Acme Product Showcase',
            'Annual Anvil Drop Gala',
            'Bedrock Community Dig',
            'Toontown Charity Run',
            'Jellystone Picnic Fundraiser',
            "Yogi's Annual Basket Drive",
            'Orbit City Space Walk',
            'Spacely Sprockets Job Fair',
            'Frostbite Falls Winter Mixer',
            'Wossamotta U Alumni Dinner',
            'Moose Lodge Annual Meeting',
            'Looney Tunes Film Night',
            'Duck Season Awareness Gala',
            'Fudd Foundation Gala',
            "Foghorn's Open Mic Night",
            'Animaniac Arts Festival',
            'Warner Lot Studio Tour',
            "Pinky & Brain's World Domination Bake Sale",
            "Snidely's Villain Reform Workshop",
            'Do-Right Annual Lecture Series',
            'Boris & Natasha Cultural Exchange',
            'Seussical Gala',
            'Thneedville Green Summit',
            "Who's Who in Whoville Awards",
            'Lorax Tree Planting Day',
            'Truffula Forest Restoration Walk',
            'Grinch Rehabilitation Luncheon',
            'Zummzian Innovation Forum',
            'Nool Hills Nature Hike',
            'Diffendoofer School Auction',
            'Sneetch Beach Cleanup',
            'Oobleck Science Fair',
            'Cartoon Classic Car Show',
            'Hanna-Barbera Heritage Dinner',
            'Yogi Memorial Cup',
            'Scooby Snack Tasting Night',
            'Top Cat Neighbourhood Watch BBQ',
            'Quickdraw McGraw Rodeo',
            'Huckleberry Hound Talent Show',
            'Peabody & Sherman Time Travellers Banquet',
        ];
    }
}
