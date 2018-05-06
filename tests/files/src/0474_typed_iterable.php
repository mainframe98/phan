<?php

/**
 * @param iterable<string,int> $isi
 */
function expect_iterable_string_int($isi) {
    foreach ($isi as $k => $v) {
        echo strlen($v);
        echo intdiv($k, $v);
    }
}

/**
 * @param Traversable<string, int> $tsi
 */
function expect_traversable_string_int(Traversable $tsi) {
    foreach ($tsi as $k => $v) {
        echo strlen($v);
        echo intdiv($k, $v);
    }
}
/**
 * @param Traversable<int,int> $tii
 * @param Traversable<string,int> $tsi
 * @param Traversable<string,string> $tss
 */
function expect_traversables(Traversable $tii, Traversable $tsi, Traversable $tss) {
    expect_traversable_string_int($tii);
    expect_traversable_string_int($tsi);
    expect_traversable_string_int($tss);
    // TODO: Make a subset of the below casts to iterable<TKey,TValue> emit appropriate warnings
    expect_iterable_string_int($tii);
    expect_iterable_string_int($tsi);
    expect_iterable_string_int($tss);
}

function test_iterable(string $s, int $i) {
    expect_iterable_string_int([$s => $i]);
    expect_iterable_string_int([$s => $s]);
    expect_iterable_string_int([$i => $s]);

    expect_traversable_string_int([$s => $i]);  // should warn
}
// TODO: Add tests of casting generators and subclasses of Traversable to iterable (and implement the functionality)
