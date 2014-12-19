Description of the algorithm ([Русская версия](https://github.com/wartur/yii-sorter/blob/master/ALGORITHM.ru.md))
=================================================================================================================

(Sorry for my english. I'm using google translate)

The algorithm is based on sparse arrays

Sparse array
------------
Let us assume that we have a sparse array with entries indexed in ascending order.
For example, consider an array output equal to 16 [0..16].
```
4
8
12
```

In such an array, you can insert a new record between the two neighboring
just one write operation - assign a new index, the range of which is between
these neighbors. Most inserts a compromise is to get the arithmetic mean
between two adjacent indexes.
```
4
8
10 <<<
12
```

Free Indexes eventually come to an end, and insert the next entry
may be a conflict of indices.
```
4
8
10
<<< I want to insert another record for the position №4
11
12
```

In order to prevent conflicts need to normalize the sparse array,
spreading codes in a way that would generate free space to insert another record
```
2
4
6
8 - this is a new record for the position №4
10
12
```

Next, the whole point of improving the algorithm boils down to a sparse
array to require as little as possible normalization of operations.
This requires frequent operation process, who live according to
different laws of mathematics.

Advantages of this algorithm is that it "pulls" whole blocks
overwriting operation in the case when it is required to insert
an arbitrary place. So when using dense fields sort we would have
to rewrite the whole block of records on interchange. Here is an
example of a different algorithm, the implementation of which is trivial:
```
1 - 1
2 - 2
3 - 3
4 - 4
5 - 5
<<<
6 - 6 *
7 - 7 *	
8 - 8 * >>>
9 - 9
```
* - Asterisk marked records that are updated. From the perspective of SQL is trivial 3 teams that make about the following: 8 assign -8, then do a shift + 1 for 6-7 records with a given before, then 8 assign 6. 10 records it seems nonsense, at 20 thousand work with this in real time is not possible, subject movement over long distances.

Вigression:
> Even in this case, starting from the index requires the middle range,
> i.e. 8. In the example started with the unit for simplicity.

Minus the algorithm works on sparse arrays is that for the algorithm,
we can not fill tightly sort field. The number of entries supported
effectively reduced.
[Read more in How to correctly calculate sort field](https://github.com/wartur/yii-sorter/blob/master/ALGORITHM.md#user-content-Read-more-in-How-to-correctly-calculate-sort-field)

The second problem is the complexity of the implementation of the algorithm

Work at normal distribution
---------------------------
When using the normal distribution is considered that the need
to insert another record between the two border. This means that
the first value to the insert will be equal to (0 + 16) / 2 = 8,
and the second (8 + 16) / 2 = 12, the third (12 + 16) / 2 = 14 and so on ...

Assuming a normal distribution, that is, insertion into arbitrary
locations on the normal distribution does not need to normalize
the audio space, since all records form the next space between
the sparse index.

Meaning the use of the normal distribution in its pure form is not,
as in real life user wishes not accidental. In the above example,
at a constant insertion into the end of the queue after 5-6 iterations,
we will reach between 15..16 degradation and require normalization.
This is one example of linear operations. Such operations require
additional processing by linear laws of mathematics.

Optimization of linear operations
---------------------------------
#### Insert at the beginning / end of the list

As a first example of linear operations take the insert at the
beginning / end of the list, these operations are very frequent in
everyday life. In practice, the new entries are added to the
beginning / end of the list, and then move somewhere else.
Adding to the end of the list is not determined by the normal law.
This is a linear operation.

Take another power array 0..64. EXAMPLE work if we use a standard
insert between two values
```
32
48
56
60
62
63
// Total 6 iterations.
```

As you can see, it did not take so many iterations that have been degraded.
In addition, you can watch at the end of the list is very bad rarefaction array,
and at first too sparse. All this means that for linear insertion end of the list
is required to use a linear function that will generate the next index at
specified intervals. To do this, we define a parameter called
sparse Power (MP) by default. Let's try to make the next insert values with MR = 4
```
32
36
40
44
48
52
56
60
// Total 7 iterations
````

In this time of iterations to get more. We are seeing that a sparse array
is not degraded at the end of the list, and he is able to take the next
entry to any position up to 2 times. Degradation is essentially uniformly
distributed across space.

That's not all. When the use of MR will exit outside the array, you should
use the standard insertion division by 2.
```
... 5 iterations
56
60
62
63
// 10 iterations ...
```
Total received 10 iterations instead of 6 original.

#### Rearrange places
Another part of the user operation is a permutation of places.
Let's see an example. In the example, we added indexes records,
as will now have to work not with anonymous records.
```
1 - 32
<<<
2 - 36
3 - 40 >>>
4 - 44
```
When inserted into the center of the list have to use a standard mechanism
inserted into the rarefied space. Let's try to start to rearrange 2 recording sites.
```
// iteration 1
1 - 32
<<<
3 - 34
2 - 36 >>>
4 - 44

// iteration 2
1 - 32
<<<
2 - 33
3 - 34 >>> 
4 - 44

// degradation
```
We see a very high rate of degradation at the most common operations.
This operation must be treated. Handled it very simply,
we just change the recording sites.
```
// iteration 0
1 - 32
<<<
2 - 36
3 - 40 >>>
4 - 44

// iteration 1
1 - 32
<<<
3 - 36
2 - 40 >>>
4 - 44

// iteration 2
1 - 32
<<<
2 - 36
3 - 40 >>>
4 - 44
// ..... and so on to infinity
```
In this case, the degradation does not occur at all.
If desired, you can sort though the bubble.

The normalization
-----------------
As mentioned above algorithm can delay occurrence of an event that
requires a rewrite unit records still once the event comes this problem
can fight different ways. The easiest way to be a regular normalization
rarefied space the entire array after a certain period of time,
in this case, we set some parameters of strength sparse array
and ask the user to observe them for a certain period of time.
In our example, we will ask not to insert in the same place for more
than 2 times for example not more than once per day (after normalization
to happen at regular serious lag system). Even if we take the normal
setting, it will be no more than 15 times in one bed per night.
All the same, it is very small. That would not bother the user constraints,
we must be able to resolve the problem areas automatically.
For this normalization was created on the fly. There's also a separate
type of normalization - normalization of the extreme,
as a special case of regular normalization.

#### Normalization on the fly
That is such a normalization that is trying to resolve the conflict when
it is detected in a limited space. The problem is to ensure that on the
one hand to touch as little as possible to minimize lag records system,
on the other hand to make the distribution of the operation so that the
degradation rate would be maximally low. For this we introduce an additional
parameter algorithm Minimum Local Power sparse (MLMR). This option ensures
a certain level of quality distribution, below which the algorithm can not
fall, in consequence of which he has to take other solutions to resolve
the conflict. How to use this parameter will be explained later.

We now turn to the description of the process of normalization on the fly.
To do this we need to increase the capacity of the newly sparse array.
Now he is a [0..512]. Next, install the MP = 16 and = 4.
By the way MLMR denote power reduction sparse arrays (MRM).

Unfortunately, the sample will be rather cumbersome:
```
1 - 256 >>> ... >>>
2 - 272
3 - 288
4 - 304
5 - 320
6 - 336
7 - 352
8 - 368
9 - 384
<<< ... <<<
10 - 400
11 - 416
12 - 432
13 - 448
14 - 464
15 - 480
16 - 496
```

Now we cycled to rearrange the first position after the 9th until such conflict.
It is easy to calculate that degradation occurs after 4 iterations.
```
5 - 320 >>>
6 - 336!
7 - 352
8 - 368
9 - 384
1 - 392 => *
2 - 396
3 - 398
4 - 399
<<< conflict!
10 - 400
11 - 416
12 - 432
13 - 448 => *
14 - 464
15 - 480
16 - 496!
```

We now begin the process of normalization. This requires two select border recording.
Equal to the width of the view. In this case, the 4-position. Number 4 is taken as
the number of shifts of 1 to get a MP = 16. That is 1 << 4 = 16. In fact, all the settings
to store bits in units of displacement, since then it is more convenient to consider
some amount (note: if you look at low installation occurs in reality the last "free"
bit to 1 or 0 to determine priorities for the insertion of a new record in the facility list).

Next on the band looks for a local power sparse (LMR). This requires to take
the number of entries recorded in the range between 392 and 448 (indicated by "").
If the record is outside the movable range, it is necessary to add to this amount.
In our case, the number of entries 6 + 1 = 7. Next we get the difference 448-392 = 56 and start looking for
a LMR that the allocation of 7 records fit in this range.
Let's start with the maximum, which is almost always not fit 16 (1 << 4) 7 + 16 = 128, 128 <= 56 = false.
Looking on. 8 (1 << 3) + 7 = 56 8, 62 <= 56 = false. Looking on. 4 (1 << 2) 7 + 4 = 32, 32 <= 56 = true Well,
we have found a new LMR this range. +16,8,4 You see in sum,
this additional reserved space between the boundary 392 and 448.
Records Otherwise, payment will be back to back, which reduces
the quality of the sparse which is obtained after normalization to the range.

A small digression 1:
> If the search LMR <MLMR, it is required to make all the steps
> to search again with twice the depth of view. That is, if we have something did not grow together,
> then we'd have to take a range of 336 to 496 c (indicated by "!").
> If you still do not find it, again double the viewing.

A small digression 2:
> Width of view of 4 selected because the amount of displacement is exactly
> the number of iterations to be executed for the conflict.
> We take a block of records from the sort field equal to 392 to 448.
> One of the unit is normalized (LMR == MR), the second part is degraded (LMR <MR).
>
> By the way, in combat conditions with a high degree of probability, both parts will be degrading.

Now we want to distribute the LMR these records in this range, leaving room for conflicting entries.
This algorithm I will not explain, because it can be done in different ways,
the result is the following result.
```
6 - 336
7 - 352
8 - 368
9 - 384
1 - 392
2 - 420 <<<
3 - 424
4 - 428
5 - 432 +++ normalized space, added a new entry
10 - 436 
11 - 440
12 - 444 <<<
13 - 448
14 - 464
15 - 480
16 - 496
```
There are two special situations in the allocation that can be identified and optimized.
For example, if the search is one of the boundaries of the search turned out to be outside
the occupied part of the array. For Example:
```
1 - 256
<<< ... <<<
2 - 272
3 - 288
4 - 304
5 - 320
6 - 336
7 - 352
8 - 368
9 - 384
10 - 400
11 - 416
12 - 432
13 - 448
14 - 464
15 - 480
16 - 496 >>> ... >>>
```
The result of such a cyclic permutation:
```
1 - 256
<<< conflict!
13 - 257
14 - 258
15 - 260
16 - 264 => *
2 - 272
3 - 288
4 - 304
5 - 320
6 - 336
7 - 352
8 - 368
9 - 384
10 - 400
11 - 416
12 - 432 >>>
```
Then provided, if the initial sparse array to the border conflict
is sufficient space (264 - 0 = 264 in 5 positions for distribution),
it is possible to carry out the distribution from one end to the other
using the parameter MR. The result will be:
```
1 - 184
12 - 200 +++ normalized space, added a new entry
13 - 216
14 - 232
15 - 248
16 - 264
2 - 272
3 - 288
4 - 304
5 - 320
6 - 336
7 - 352
8 - 368
9 - 384
10 - 400
11 - 416
12 - 432
```

The second situation is when the search went beyond the boundaries of two current space.
This is a special case of regular normalization.

#### Regular normalization
That is, this normalization, which is trying to recover MP by default
on the entire range of values. Works very simple, we take the number of values,
LMR looking for this range and allocates space.
If LMR <MLMR then activated extreme normalization

#### Extreme normalization
That is, this normalization, which is trying to somehow allocate space and to resolve the conflict,
up to the full capacity of power sparse array. To search for LMR value is lower than MLMR.
In essence, no different from a regular normalization, it is just a special case that occurs
in principle not be. This means that the algorithm works in an emergency mode and a huge lags.

How to store the sort field
---------------------------
Sort this field INT c unique index. Uniqueness is required to ensure consistency of information.
Have a negative value is required to enable rearrangement.
So permutation can be done through negative values.

How to calculate the sort field
-------------------------------
The algorithm has 3 parameters. MPM / MR / MLMR

By default, a 32-bit system, these parameters are 30/15/4.
(maximum number of entries to the effective operation of 32768)

For a 64-bit system, these parameters are 62/31/6
(maximum number of entries to the effective operation of 2147483648)

The right thing to use INT as PK, and BIGINT as a sort field.

For setting up simple. MRM sets used range. If you need to use a range of aggressive,
it increases the MP. Then the operation of normalization will work less time.
If you want to make a big difference on the contrary, the Seals less,
but because of this there will be more opiratsy optimization.

Notes on MRM. As you noticed it too is defined in the bit offset.
With that, it is the bit offset is equal to 1073741824 that is half INT.
Historically it also convenient to assume without overflow MAX INT as INT - 1 + INT.
This formula is used to determine the upper limit of sparse array.
Furthermore, this number is used as the first value for the insert.

In total
--------
The algorithm is very robust to various humiliations, such as a cyclic permutation of records
in the same place in the middle of the list, move the recording by the bubble ring inserted
into the end of the list, has a notice in the log, which obviously tells the administrator
that the algorithm in a short time switch to emergency mode. In emergency mode, it is,
though with great loss of performance, but will continue to work, to the physical
exhaustion of a sparse array.

By the way, the use of this algorithm does not cancel the option of using
a simple algorithm used above, more than they can complement each other.
So, moving up 10 places recording and overwrite all ten blocks
are not strongly affect performance, and moving long distances or
insert in the center of the list can be done by using this algorithm.

Thank you!
----------
Thank you for reading, I hope it was interesting!
