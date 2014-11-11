### ALGORITHM

link to habrahabr


/*
 * Это выражение работает как-то так:
 * (
 * количество элементов - $this->dischargeSpaceBitSize
 * умножить на глубину просмотра - $doubleSearchMultiplier
 * умножить на просмотр в 2 стороны - 2
 * умножить текущий найденый размер - $naturalSpaceSize
 * )
 * прибавить 1 место для очередного элемента
 * вычесть 2 крайних элемента - $naturalSpaceSize (итого 1-2 = -1)
 */