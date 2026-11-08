# VPC Isolada
resource "aws_vpc" "gci_vpc" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "gci-vpc"
  }
}

# Internet Gateway para tráfego da Subnet Pública
resource "aws_internet_gateway" "gci_igw" {
  vpc_id = aws_vpc.gci_vpc.id

  tags = {
    Name = "gci-igw"
  }
}

# Subnet Pública (Aplicação Yii / Cluster k3s)
resource "aws_subnet" "public_subnet" {
  vpc_id                  = aws_vpc.gci_vpc.id
  cidr_block              = "10.0.1.0/24"
  map_public_ip_on_launch = true
  availability_zone       = "${var.aws_region}a"

  tags = {
    Name = "gci-public-subnet"
  }
}

# Subnet Privada (PostgreSQL Nativo)
resource "aws_subnet" "private_subnet" {
  vpc_id            = aws_vpc.gci_vpc.id
  cidr_block        = "10.0.2.0/24"
  availability_zone = "${var.aws_region}a"

  tags = {
    Name = "gci-private-subnet"
  }
}

# Tabela de Rotas da Subnet Pública
resource "aws_route_table" "public_rt" {
  vpc_id = aws_vpc.gci_vpc.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.gci_igw.id
  }

  tags = {
    Name = "gci-public-rt"
  }
}

resource "aws_route_table_association" "public_assoc" {
  subnet_id      = aws_subnet.public_subnet.id
  route_table_id = aws_route_table.public_rt.id
}