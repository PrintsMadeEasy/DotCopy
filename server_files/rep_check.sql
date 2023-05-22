#-- Getting the max ID from this table between the master and slave is a good way to compare if they are syncronized
SELECT MAX(ID) FROM projectsordered;