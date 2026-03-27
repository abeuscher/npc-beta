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
            'acme.example.com',
            'kc-company.example.com',
            'bedrock.example.com',
            'spacely-sprockets.example.com',
            'cogswell-cogs.example.com',
            'jellystone.example.com',
            'frostbitefalls.example.com',
            'wossamotta.example.com',
            'toon-town.example.com',
            'megacorp.example.com',
            'globochem.example.com',
            'seussworks.example.com',
            'thneedville.example.com',
            'whoville.example.com',
            'nool-hills.example.com',
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

    public static function productFranchises(): array
    {
        return [
            // Action figures
            'Transformers', 'G.I. Joe', 'He-Man', 'Masters of the Universe',
            'Thundercats', 'SilverHawks', 'Voltron', 'M.A.S.K.',
            // Space & sci-fi
            'Star Wars', 'Ghostbusters', 'Sectaurs', 'Starriors',
            // Girls lines
            'My Little Pony', 'Care Bears', 'Strawberry Shortcake',
            'Rainbow Brite', 'Jem and the Holograms', 'Pound Puppies',
            'Cabbage Patch Kids', 'Barbie',
            // Gross / novelty
            'Madballs', 'Garbage Pail Kids', 'Boglins',
            // Construction & vehicles
            'Micro Machines', 'Hot Wheels', 'Matchbox', 'LEGO',
            // Electronic games
            'Simon', 'Speak & Spell', 'Lite-Brite', 'View-Master',
            // Board games
            'Trivial Pursuit', 'Operation', 'Hungry Hungry Hippos',
            'Battleship', 'Connect Four', 'Boggle', 'Perfection',
            // Drawing & craft
            'Spirograph', 'Etch A Sketch',
        ];
    }

    public static function productCharacters(): array
    {
        return [
            // Transformers
            'Optimus Prime', 'Megatron', 'Bumblebee', 'Starscream',
            'Grimlock', 'Soundwave', 'Jazz', 'Ironhide', 'Sideswipe',
            'Springer', 'Wheeljack', 'Powerglide',
            // G.I. Joe
            'Snake Eyes', 'Duke', 'Cobra Commander', 'Destro',
            'Zartan', 'Roadblock', 'Storm Shadow', 'Lady Jaye',
            'Flint', 'Jinx', 'Serpentor', 'Crimson Guard',
            // He-Man
            'Castle Grayskull', 'Skeletor', 'Battle Cat', 'Beast Man',
            'Evil-Lyn', 'Hordak', 'Trapjaw', 'Mer-Man', 'Stinkor',
            // Care Bears
            'Tenderheart Bear', 'Grumpy Bear', 'Birthday Bear',
            'Wish Bear', 'Funshine Bear', 'Love-a-Lot Bear',
            // TMNT
            'Raphael', 'Leonardo', 'Michelangelo', 'Donatello',
            'Shredder', 'Krang', 'Bebop', 'Rocksteady',
            // Thundercats
            'Lion-O', 'Panthro', 'Tygra', 'Cheetara',
            'Mumm-Ra', 'Wilykit', 'Wilykat',
            // SilverHawks
            'Quicksilver', 'Steelheart', 'MonStar', 'Windhammer',
            // Star Wars
            'Darth Vader', 'Luke Skywalker', 'Han Solo', 'R2-D2',
            'Yoda', 'AT-AT Walker', 'Boba Fett', 'Jabba the Hutt',
            // Ghostbusters
            'Slimer', 'Stay Puft', 'Zuul', 'Gozer',
            // Jem
            'Jem', 'Pizzazz', 'Synergy', 'Roxy',
            // My Little Pony
            'Twilight', 'Butterscotch', 'Applejack', 'Minty',
            // Madballs
            'Skull Face', 'Oculus Orbus', 'Crack Head', 'Slobulus',
        ];
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
