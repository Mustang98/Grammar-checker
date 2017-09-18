import os

file = open("../Vocabulary/ruscorpora.txt", "r", -1, "utf-8")
file_good = open("../Vocabulary/russian_corrected.txt", "w", -1, "utf-8")
file_bad = open("../Vocabulary/incorrect_words.txt", "w", -1, "utf-8")
file_rare = open("../Vocabulary/rare_words.txt", "w", -1, "utf-8")

min_frequency = 20
vocabulary = {}

for line in file:
    frequency = int(line.split("	")[0])
    word = line.split("	")[1][:-1]
    is_incorrect = False
    space_cnt = 0
    for i in word:
        if i != "-" and i != "ё" and i != "Ё" and (i < "А" or i > "я" or (i > "Я" and i < "а")):
            is_incorrect = True
            break
        if i is "-":
            space_cnt += 1
            if space_cnt > 1:
                is_incorrect = True
                break
    if is_incorrect:
        file_bad.write(line)
    else:
        word = word.lower()
        if word in vocabulary:
            vocabulary[word] += frequency
        else:
            vocabulary[word] = frequency

for word in sorted(vocabulary):
    if vocabulary[word] < min_frequency:
        file_rare.write(word + " " + str(vocabulary[word]) + "\n")
    else:
        file_good.write(word + " " + str(vocabulary[word]) + "\n")