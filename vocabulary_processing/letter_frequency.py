import os

file = open("../Vocabulary/russian_corrected.txt", "r", -1, "utf-8")

letters = {}

for line in file:
    frequency = int(line.split(" ")[1][:-1])
    word = line.split(" ")[0];  # [:-1]

    for letter in word:
        if letter in letters:
            letters[letter] += frequency
        else:
            letters[letter] = frequency

freq_string = ""

for letter in sorted(letters, key = lambda a : letters[a], reverse=True):
    freq_string += letter

print(freq_string)