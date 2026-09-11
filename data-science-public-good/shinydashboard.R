#Kyra Evans
#June 2024

# Load necessary libraries
library(shiny)
library(shinythemes)
library(tmap)
library(sf)
library(dplyr)
library(tigris)
library(ggplot2)
library(reshape2)
library(factoextra)
library(corrplot)

# Load dataset
adata <- readRDS("data/acs_places_rural_food.RDS")

# Select relevant variables and drop geometry
adata <- adata %>%
  st_drop_geometry() %>%
  select(GEOID, county, pct_minority, DIABETES, pct_snap, pct_no_bach, med_income)

# Check for missing values and remove rows with missing values
adata <- na.omit(adata)

# Perform k-means clustering
scaled_data <- scale(adata[, -c(1, 2)])
set.seed(123)
km <- kmeans(scaled_data, 3, nstart = 25)
adata$cluster <- factor(km$cluster, levels = 1:3, labels = c("Disadvantaged", "Intermediate", "Advantaged"))

# Load county shapefile
counties <- counties(cb = TRUE, resolution = "20m")

# Merge your data with the shapefile
county_data <- counties %>%
  left_join(adata, by = "GEOID")

# Define a color palette
color_palette <- c("#FF9999", "#66B2FF", "#99FF99")  # Custom color palette for clusters

# Shiny UI
ui <- fluidPage(
  theme = shinytheme("cerulean"),
  titlePanel("Predicting Health Outcomes Using Cluster Analysis"),
  
  sidebarLayout(
    sidebarPanel(
      selectInput("variable", "Select Variable to Visualize:", 
                  choices = colnames(adata)[-c(1, 2, ncol(adata))],
                  selected = colnames(adata)[3])
    ),
    
    mainPanel(
      tabsetPanel(
        tabPanel("Cluster Centers", 
                 plotOutput("clusterbarplot"), 
                 h5("Cluster Centers for Variables"),
                 p("This plot displays the centers of each cluster for all variables, indicating the average values.")
        ),
        tabPanel("Barplots", 
                 plotOutput("barplot"), 
                 h5("Distribution of variables across clusters"),
                 p("This plot shows how the selected variable is distributed among the different clusters.")
        ),
        tabPanel("Cluster Visualization", 
                 plotOutput("clusterPlot"), 
                 h5("Clusters in 2D Space"),
                 p("This plot visualizes the clusters in a two-dimensional space, showing the separation and grouping of data points.")
        ),
        tabPanel("Map", 
                 tmapOutput("tmap"), 
                 h5("Geographical Distribution of Clusters"),
                 p("This map displays the geographical distribution of clusters across counties.")
        ),
        tabPanel("Correlation Heatmap",
                 plotOutput("heatmapPlot"),
                 h5("Correlation Heatmap"),
                 p("This heatmap shows the correlation between different variables in the dataset.")
        ),
        tabPanel("Correlation Matrix",
                 verbatimTextOutput("correlationMatrixOutput"),
                 h5("Correlation Matrix"),
                 p("This table shows the numerical correlation coefficients between different variables.")
        )
      )
    )
  )
)

# Shiny Server
server <- function(input, output) {
  
  output$barplot <- renderPlot({
    cluster_means <- adata %>%
      group_by(cluster) %>%
      summarize(mean_value = mean(get(input$variable), na.rm = TRUE))
    
    ggplot(cluster_means, aes(x = cluster, y = mean_value, fill = cluster)) + 
      geom_bar(stat = "identity", position = "dodge") +
      scale_fill_brewer(palette = "Set3") +
      labs(title = paste("Mean", input$variable, "by Cluster"), 
           x = "Cluster", y = paste("Mean", input$variable)) +
      theme_minimal() +
      theme(plot.title = element_text(hjust = 0.5))
  })
  
  output$clusterbarplot <- renderPlot({
    rc <- data.frame(km$centers)
    rc$Cluster <- factor(rownames(rc), levels = 1:3, labels = c("Disadvantaged", "Intermediate", "Advantaged"))
    rc_long <- reshape2::melt(rc, id.vars = c("Cluster"))
    
    ggplot(rc_long, aes(Cluster, value, fill = variable)) + 
      geom_col(position = "dodge") +
      scale_fill_brewer(palette = "Set3") +
      labs(title = "Cluster Centers", x = "Cluster", y = "Value") +
      theme_minimal() +
      theme(plot.title = element_text(hjust = 0.5))
  })
  
  output$clusterPlot <- renderPlot({
    factoextra::fviz_cluster(km, data = scaled_data, 
                             ellipse.type = "euclid", 
                             star.plot = TRUE, 
                             palette = color_palette,  
                             ggtheme = theme_minimal()) +
      theme(plot.title = element_text(hjust = 0.5))
  })
  
  output$tmap <- renderTmap({
    tmap_mode("view")
    tm_shape(county_data) +
      tm_polygons(col = "cluster", 
                  palette = color_palette,  
                  title = "Cluster") +
      tm_borders() +
      tm_layout(title = "Cluster Analysis of Counties",
                legend.outside = TRUE,
                title.size = 1.5,
                title.position = c("left", "top"),
                legend.text.size = 0.8)
  })
  
  output$heatmapPlot <- renderPlot({
    correlation_matrix <- cor(adata[, -c(1, 2, ncol(adata))])
    corrplot(correlation_matrix, method = "color", col = colorRampPalette(c("black", "green", "gold"))(200), 
             type = "lower", order = "hclust", addCoef.col = "black", tl.col = "black", tl.srt = 45)
  })
  
  output$correlationMatrixOutput <- renderPrint({ 
    correlation_matrix <- cor(adata[, -c(1, 2, ncol(adata))])
    print(correlation_matrix)
  })
}

# Run the application 
shinyApp(ui = ui, server = server)
