<?php

// These are hard to test because these functions actually exist
// in PHP 8. So, you'll have to go in and rename the polyfills
// in polyfills.php and also rename the functions contained
// herein to test them.

describe("array_is_list", function () {
    it('returns true for an empty array', function () {
        $array = [];

        $result = array_is_list($array);

        expect($result)->toBeTrue();
    });

    it('returns true for a sequential array', function () {
        $array = [1, 2, 3, 4];

        $result = array_is_list($array);

        expect($result)->toBeTrue();
    });

    it('returns false for an associative array', function () {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = array_is_list($array);

        expect($result)->toBeFalse();
    });

    it('returns false for a mixed array', function () {
        $array = [1, 'a' => 2, 3, 'b' => 4];

        $result = array_is_list($array);

        expect($result)->toBeFalse();
    });

    it('returns false for a non-sequential array', function () {
        $array = [1, 2, 4 => 3, 4];

        $result = array_is_list($array);

        expect($result)->toBeFalse();
    });

});

describe("str_starts_with", function () {
    it("returns true if the haystack starts with the needle", function () {
        $haystack = "Hello, world!";
        $needle = "Hello";

        $result = str_starts_with($haystack, $needle);

        expect($result)->toBeTrue();
    });

    it("returns false if the haystack does not start with the needle", function () {
        $haystack = "Hello, world!";
        $needle = "World";

        $result = str_starts_with($haystack, $needle);

        expect($result)->toBeFalse();
    });

    it("returns true if the needle is an empty string", function () {
        $haystack = "Hello, world!";
        $needle = "";

        $result = str_starts_with($haystack, $needle);

        expect($result)->toBeTrue();
    });

    it("returns false if the haystack is not a string", function () {
        $haystack = 123;
        $needle = "Hello";

        $result = str_starts_with($haystack, $needle);

        expect($result)->toBeFalse();
    });

    it("returns false if the needle is not a string", function () {
        $haystack = "Hello, world!";
        $needle = 123;

        $result = str_starts_with($haystack, $needle);

        expect($result)->toBeFalse();
    });
});

describe("str_contains", function () {
    it('returns true if the haystack contains the needle', function () {
        $haystack = 'Hello, world!';
        $needle = 'world';

        $result = str_contains($haystack, $needle);

        expect($result)->toBeTrue();
    });

    it('returns false if the haystack does not contain the needle', function () {
        $haystack = 'Hello, world!';
        $needle = 'foo';

        $result = str_contains($haystack, $needle);

        expect($result)->toBeFalse();
    });

    it('returns true if the needle is an empty string', function () {
        $haystack = 'Hello, world!';
        $needle = '';

        $result = str_contains($haystack, $needle);

        expect($result)->toBeTrue();
    });

    it('returns false if the haystack is not a string', function () {
        $haystack = 123;
        $needle = 'Hello';

        $result = str_contains($haystack, $needle);

        expect($result)->toBeFalse();
    });

    it("returns false if the needle is not a string", function () {
        $haystack = "Hello, world!";
        $needle = 123;

        $result = str_contains($haystack, $needle);

        expect($result)->toBeFalse();
    });
});

describe("object_keys", function () {
    it('returns an empty array for an empty array', function () {
        $array = [];

        $result = object_keys($array);

        expect($result)->toBe([]);
    });

    it('returns an array of keys for an associative array', function () {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = object_keys($array);

        expect($result)->toBe(['a', 'b', 'c']);
    });

    it('returns an array of keys for a sequential array', function () {
        $array = [1, 2, 3, 4];

        $result = object_keys($array);

        expect($result)->toBe([0, 1, 2, 3]);
    });

    it('returns an array of keys for an object', function () {
        $object = (object) ['a' => 1, 'b' => 2, 'c' => 3];

        $result = object_keys($object);

        expect($result)->toBe(['a', 'b', 'c']);
    });

    it('returns an empty array for an empty object', function () {
        $object = (object) [];

        $result = object_keys($object);

        expect($result)->toBe([]);
    });
});
