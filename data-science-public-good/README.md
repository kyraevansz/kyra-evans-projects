# Data Science for the Public Good

R Shiny dashboard analyzing ACS public health data to identify community 
clusters (rural areas facing food access and health disparities). Uses 
k-means clustering and tmap for geographic visualization.

Findings informed a policy memo presented to VASEM members and legislators.

## Files
- `shinydashboard.R` — main dashboard script (k-means clustering, tmap)
- `acs_places_rural_food.RDS` — ACS Places dataset (rural food access & health indicators)

## Tech
R, Shiny

## Packages used
- `shiny`
- `shinythemes`
- `tmap`
- `sf`
- `dplyr`
- `tigris`
- `ggplot2`
- `reshape2`
- `factoextra`
- `corrplot`

## How to run
1. Install R and RStudio if you don't already have them.
2. Install the required packages:
```r
   install.packages(c("shiny", "shinythemes", "tmap", "sf", "dplyr", 
                       "tigris", "ggplot2", "reshape2", "factoextra", "corrplot"))
```
3. Download both files (`shinydashboard.R` and `acs_places_rural_food.RDS`) into the same folder.
4. Open `shinydashboard.R` in RStudio and click **Run App** (or run `shiny::runApp()` from that folder in the R console).
